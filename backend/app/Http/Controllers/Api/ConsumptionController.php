<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ConsumptionSnapshots;
use App\Services\CorrectConsumption;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumptionController extends Controller
{
    public function dates(Request $request): JsonResponse
    {
        $dates = $request->user()->consumptions()
            ->selectRaw('local_date, COUNT(*) AS meal_count, SUM(calories) AS calories, SUM(protein) AS protein')
            ->groupBy('local_date')->orderByDesc('local_date')->limit(31)->get();

        return response()->json(['data' => $dates]);
    }

    public function update(Request $request, string $consumption, CorrectConsumption $correction): JsonResponse
    {
        $record = $request->user()->consumptions()->findOrFail($consumption);
        $validated = $request->validate([
            'meal_category_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.food_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit' => ['required', 'in:g,ml,serving,portion'],
            'items.*.portion_id' => ['required_if:items.*.unit,portion', 'nullable', 'uuid'],
        ]);
        $key = $request->header('Idempotency-Key');
        if (! $key || mb_strlen($key) > 120) {
            throw ValidationException::withMessages(['idempotency_key' => ['An Idempotency-Key header of at most 120 characters is required.']]);
        }
        $fingerprint = hash('sha256', json_encode($validated));
        $existing = $record->revisions()->where('idempotency_key', $key)->first();
        if ($existing) {
            abort_if($existing->request_fingerprint !== $fingerprint, 409, 'Idempotency key was already used with different data.');

            return response()->json(['data' => $record->load('items')]);
        }
        $input = [
            'meal_category_id' => $validated['meal_category_id'],
            'notes' => $validated['notes'] ?? null,
            'reason' => 'Meal edited by user',
        ];
        if (isset($validated['items'])) {
            $input['correction_items'] = $validated['items'];
        }
        $updated = $correction->handle($record, $request->user(), $input, $key, $fingerprint);

        return response()->json(['data' => $updated]);
    }

    public function index(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        $consumptions = $request->user()->consumptions()->with('items')
            ->whereDate('local_date', $date)->orderBy('eaten_at')->orderBy('id')->get();

        return response()->json(['data' => $consumptions]);
    }

    public function store(Request $request, ConsumptionSnapshots $builder): JsonResponse
    {
        $validated = $request->validate([
            'meal_category_id' => ['required', 'uuid'],
            'eaten_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'recipe_id' => ['nullable', 'uuid'],
            'servings' => ['required_with:recipe_id', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'items' => ['required_without:recipe_id', 'array', 'min:1', 'max:100'],
            'items.*.food_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit' => ['required', 'in:g,ml,serving,portion'],
            'items.*.portion_id' => ['required_if:items.*.unit,portion', 'nullable', 'uuid'],
        ]);
        $key = $request->header('Idempotency-Key');
        if (! $key || mb_strlen($key) > 120) {
            throw ValidationException::withMessages(['idempotency_key' => ['An Idempotency-Key header of at most 120 characters is required.']]);
        }
        if (isset($validated['recipe_id'], $validated['items'])) {
            throw ValidationException::withMessages(['items' => ['Log either a recipe or individual foods, not both.']]);
        }
        $fingerprint = hash('sha256', json_encode($validated));
        $existing = $request->user()->consumptions()->where('idempotency_key', $key)->first();
        if ($existing) {
            abort_if($existing->request_fingerprint !== $fingerprint, 409, 'Idempotency key was already used with different data.');

            return response()->json(['data' => $existing->load('items')]);
        }
        $category = $request->user()->mealCategories()->where('is_active', true)->findOrFail($validated['meal_category_id']);
        $eatenAt = CarbonImmutable::parse($validated['eaten_at'])->utc();
        $timeZone = $request->user()->settings?->time_zone ?? 'Europe/Sofia';
        $localDate = $eatenAt->setTimezone($timeZone)->toDateString();
        $recipe = isset($validated['recipe_id'])
            ? $request->user()->recipes()->with(['ingredients.food.nutrition', 'ingredients.food.portions'])->findOrFail($validated['recipe_id'])
            : null;
        if ($recipe?->is_archived) {
            throw ValidationException::withMessages(['recipe_id' => ['Archived recipes cannot be logged.']]);
        }
        $snapshots = $recipe
            ? $builder->recipe($recipe, (float) $validated['servings'])
            : array_map(fn (array $item): array => $builder->food($request->user(), $item), $validated['items']);
        $totals = $builder->totals($snapshots);

        [$consumption, $created] = DB::transaction(function () use ($request, $category, $eatenAt, $localDate, $validated, $key, $fingerprint, $totals, $snapshots, $recipe) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = $request->user()->consumptions()->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_if($existing->request_fingerprint !== $fingerprint, 409, 'Idempotency key was already used with different data.');

                return [$existing->load('items'), false];
            }
            $consumption = $request->user()->consumptions()->create([
                'meal_category_id' => $category->id, 'eaten_at' => $eatenAt,
                'local_date' => $localDate, 'notes' => $validated['notes'] ?? null,
                'idempotency_key' => $key, 'request_fingerprint' => $fingerprint,
                'source_type' => $recipe ? 'recipe' : 'foods',
                'source_id' => $recipe?->id,
                'source_name' => $recipe?->name,
                'source_quantity' => $recipe ? $validated['servings'] : null,
                'source_unit' => $recipe ? 'serving' : null,
                ...$totals,
            ]);
            $consumption->items()->createMany($snapshots);

            return [$consumption->load('items'), true];
        });

        return response()->json(['data' => $consumption], $created ? 201 : 200);
    }
}
