<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CatalogMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipeController extends Controller
{
    public function update(Request $request, string $recipe, CatalogMutation $mutation): JsonResponse
    {
        $record = $request->user()->recipes()->findOrFail($recipe);
        $validated = $request->validate($this->rules());
        $result = $mutation->update($request, 'recipe', $record->id, $validated, function () use ($record, $request, $validated): array {
            $this->validateIngredients($request->user(), $validated['ingredients']);
            $record->update(['name' => $validated['name'], 'yield_servings' => $validated['yield_servings']]);
            $record->ingredients()->delete();
            foreach ($validated['ingredients'] as $index => $ingredient) {
                $record->ingredients()->create([
                    'food_id' => $ingredient['food_id'], 'food_portion_id' => $ingredient['portion_id'] ?? null,
                    'quantity' => $ingredient['quantity'], 'unit' => $ingredient['unit'], 'sort_order' => $index,
                ]);
            }

            return $record->refresh()->load(['ingredients.food.nutrition', 'ingredients.portion'])->toArray();
        });

        return response()->json(['data' => $result]);
    }

    public function index(Request $request): JsonResponse
    {
        $recipes = $request->user()->recipes()->where('is_archived', false)
            ->with(['ingredients.food.nutrition', 'ingredients.portion'])
            ->orderBy('name')->orderBy('id')->get();

        return response()->json(['data' => $recipes]);
    }

    public function show(Request $request, string $recipe): JsonResponse
    {
        $record = $request->user()->recipes()->with(['ingredients.food.nutrition', 'ingredients.portion'])->findOrFail($recipe);

        return response()->json(['data' => $record]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $key = $request->header('Idempotency-Key');
        if (! $key || mb_strlen($key) > 120) {
            throw ValidationException::withMessages(['idempotency_key' => ['An Idempotency-Key header of at most 120 characters is required.']]);
        }
        $fingerprint = hash('sha256', json_encode($validated));
        $existing = $request->user()->recipes()->where('idempotency_key', $key)->first();
        if ($existing) {
            abort_if($existing->request_fingerprint !== $fingerprint, 409, 'Idempotency key was already used with different data.');

            return response()->json(['data' => $existing->load(['ingredients.food.nutrition', 'ingredients.portion'])]);
        }
        $this->validateIngredients($request->user(), $validated['ingredients']);
        [$recipe, $created] = DB::transaction(function () use ($request, $validated, $key, $fingerprint) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = $request->user()->recipes()->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_if($existing->request_fingerprint !== $fingerprint, 409, 'Idempotency key was already used with different data.');

                return [$existing, false];
            }
            $recipe = $request->user()->recipes()->create([
                'name' => $validated['name'], 'yield_servings' => $validated['yield_servings'],
                'idempotency_key' => $key, 'request_fingerprint' => $fingerprint,
            ]);
            foreach ($validated['ingredients'] as $index => $ingredient) {
                $recipe->ingredients()->create([
                    'food_id' => $ingredient['food_id'], 'food_portion_id' => $ingredient['portion_id'] ?? null,
                    'quantity' => $ingredient['quantity'], 'unit' => $ingredient['unit'], 'sort_order' => $index,
                ]);
            }

            return [$recipe, true];
        });

        return response()->json(['data' => $recipe->load(['ingredients.food.nutrition', 'ingredients.portion'])], $created ? 201 : 200);
    }

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'yield_servings' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.food_id' => ['required', 'uuid'],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'ingredients.*.unit' => ['required', 'in:g,ml,serving,portion'],
            'ingredients.*.portion_id' => ['required_if:ingredients.*.unit,portion', 'nullable', 'uuid'],
        ];
    }

    /** @param array<int, array<string, mixed>> $ingredients */
    private function validateIngredients(User $user, array $ingredients): void
    {
        foreach ($ingredients as $ingredient) {
            $food = $user->foods()->with(['nutrition', 'portions'])->findOrFail($ingredient['food_id']);
            if ($food->is_archived) {
                throw ValidationException::withMessages(['ingredients' => ['Archived foods cannot be added to recipes.']]);
            }
            if ($ingredient['unit'] === 'portion') {
                if (! $food->portions->firstWhere('id', $ingredient['portion_id'] ?? null)) {
                    throw ValidationException::withMessages(['ingredients' => ['The portion does not belong to this food.']]);
                }
            } elseif ($ingredient['unit'] !== $food->nutrition->basis_unit) {
                throw ValidationException::withMessages(['ingredients' => ['No explicit conversion exists for this unit.']]);
            }
        }
    }
}
