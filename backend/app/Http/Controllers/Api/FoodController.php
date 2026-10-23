<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\CatalogMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FoodController extends Controller
{
    /**
     * Update one of your branded foods.
     *
     * Replace its nutrition and explicit portions. Existing meal snapshots are not changed. A portion used by a recipe cannot be removed. Send an Idempotency-Key header for safe retries.
     */
    public function update(Request $request, string $food, CatalogMutation $mutation): JsonResponse
    {
        $record = $request->user()->foods()->with(['nutrition', 'portions'])->findOrFail($food);
        $validated = $request->validate($this->rules(true));
        foreach ($validated['portions'] as $portion) {
            if ($portion['base_unit'] !== $validated['nutrition']['basis_unit']) {
                throw ValidationException::withMessages(['portions' => ['Portion units must match the nutrition basis; no density conversion is assumed.']]);
            }
        }

        $result = $mutation->update($request, 'food', $record->id, $validated, function () use ($record, $validated): array {
            $record->refresh()->load(['nutrition', 'portions']);
            if ($record->nutrition->basis_unit !== $validated['nutrition']['basis_unit'] &&
                ($record->portions()->exists() || RecipeIngredient::where('food_id', $record->id)->exists())) {
                throw ValidationException::withMessages(['nutrition.basis_unit' => ['Create a new food to change the basis unit of one used by portions or recipes.']]);
            }
            $keepIds = [];
            foreach ($validated['portions'] as $portion) {
                if (isset($portion['id'])) {
                    $existing = $record->portions()->findOrFail($portion['id']);
                    $keepIds[] = $existing->id;
                }
            }
            $removed = $record->portions()->whereNotIn('id', $keepIds)->pluck('id');
            if ($removed->isNotEmpty() && RecipeIngredient::whereIn('food_portion_id', $removed)->exists()) {
                throw ValidationException::withMessages(['portions' => ['A portion used by a recipe cannot be removed. Edit that recipe first.']]);
            }

            $record->update(['name' => $validated['name'], 'brand' => $validated['brand'], 'barcode' => $validated['barcode'] ?? null]);
            $record->portions()->whereIn('id', $removed)->delete();
            $record->nutrition()->update($validated['nutrition']);
            foreach ($validated['portions'] as $portion) {
                if (isset($portion['id'])) {
                    $record->portions()->findOrFail($portion['id'])->update([
                        'label' => $portion['label'], 'base_amount' => $portion['base_amount'], 'base_unit' => $portion['base_unit'],
                    ]);
                } else {
                    $record->portions()->create($portion);
                }
            }

            return $record->refresh()->load(['nutrition', 'portions'])->toArray();
        });

        return response()->json(['data' => $result]);
    }

    /**
     * Search your private food catalogue.
     *
     * The optional query searches names and brands; at most 30 active foods are returned. This does not call Open Food Facts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->validate(['query' => ['nullable', 'string', 'max:100']])['query'] ?? '';
        $foods = $request->user()->foods()->with(['nutrition', 'portions'])
            ->where('is_archived', false)
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'ilike', '%'.$query.'%')->orWhere('brand', 'ilike', '%'.$query.'%');
            })->orderBy('name')->orderBy('id')->limit(30)->get();

        return response()->json(['data' => $foods]);
    }

    /**
     * Get a branded food with nutrition and portions.
     *
     * Foods belonging to another account are not visible.
     */
    public function show(Request $request, string $food): JsonResponse
    {
        return response()->json(['data' => $request->user()->foods()->with(['nutrition', 'portions'])->findOrFail($food)]);
    }

    /**
     * Create a branded food.
     *
     * Nutrition uses an explicit g, ml, or serving basis. Household portions require food-specific conversions; density is never guessed. Send an Idempotency-Key header for safe retries.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        foreach ($validated['portions'] ?? [] as $portion) {
            if ($portion['base_unit'] !== $validated['nutrition']['basis_unit']) {
                throw ValidationException::withMessages(['portions' => ['Portion units must match the nutrition basis; no density conversion is assumed.']]);
            }
        }
        $key = $request->header('Idempotency-Key');
        if (! $key || mb_strlen($key) > 120) {
            throw ValidationException::withMessages(['idempotency_key' => ['An Idempotency-Key header of at most 120 characters is required.']]);
        }
        $fingerprint = hash('sha256', json_encode($validated));
        $existing = $request->user()->foods()->where('idempotency_key', $key)->first();
        if ($existing) {
            abort_if($existing->request_fingerprint !== $fingerprint, 409, 'Idempotency key was already used with different data.');

            return response()->json(['data' => $existing->load(['nutrition', 'portions'])]);
        }

        [$food, $created] = DB::transaction(function () use ($request, $validated, $key, $fingerprint) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = $request->user()->foods()->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_if($existing->request_fingerprint !== $fingerprint, 409, 'Idempotency key was already used with different data.');

                return [$existing->load(['nutrition', 'portions']), false];
            }
            $food = $request->user()->foods()->create([
                'name' => $validated['name'], 'brand' => $validated['brand'],
                'barcode' => $validated['barcode'] ?? null, 'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
            ]);
            $food->nutrition()->create($validated['nutrition']);
            foreach ($validated['portions'] ?? [] as $portion) {
                $food->portions()->create($portion);
            }

            return [$food->load(['nutrition', 'portions']), true];
        });

        return response()->json(['data' => $food], $created ? 201 : 200);
    }

    /** @return array<string, array<int, string>> */
    private function rules(bool $update = false): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'nutrition' => ['required', 'array'],
            'nutrition.basis_amount' => ['required', 'numeric', 'gt:0'],
            'nutrition.basis_unit' => ['required', 'in:g,ml,serving'],
            'nutrition.calories' => ['required', 'numeric', 'min:0'],
            'nutrition.protein' => ['required', 'numeric', 'min:0'],
            'nutrition.carbohydrates' => ['required', 'numeric', 'min:0'],
            'nutrition.fat' => ['required', 'numeric', 'min:0'],
            'nutrition.fibre' => ['nullable', 'numeric', 'min:0'],
            'nutrition.sugar' => ['nullable', 'numeric', 'min:0'],
            'nutrition.salt' => ['nullable', 'numeric', 'min:0'],
            'portions' => [$update ? 'required' : 'sometimes', 'array'],
            'portions.*.id' => [$update ? 'sometimes' : 'prohibited', 'uuid'],
            'portions.*.label' => ['required', 'string', 'max:100'],
            'portions.*.base_amount' => ['required', 'numeric', 'gt:0'],
            'portions.*.base_unit' => ['required', 'in:g,ml,serving'],
        ];
    }
}
