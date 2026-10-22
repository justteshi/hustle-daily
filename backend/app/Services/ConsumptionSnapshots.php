<?php

namespace App\Services;

use App\Models\Food;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ConsumptionSnapshots
{
    public function __construct(private NutritionCalculator $calculator) {}

    /** @return array<string, mixed> */
    public function food(User $user, array $item): array
    {
        $food = $user->foods()->with(['nutrition', 'portions'])->findOrFail($item['food_id']);

        return $this->snapshot($food, (float) $item['quantity'], $item['unit'], $item['portion_id'] ?? null);
    }

    /** @return array<int, array<string, mixed>> */
    public function recipe(Recipe $recipe, float $servings): array
    {
        $factor = $servings / (float) $recipe->yield_servings;
        $snapshots = [];
        foreach ($recipe->ingredients as $ingredient) {
            $snapshots[] = $this->snapshot(
                $ingredient->food,
                (float) $ingredient->quantity * $factor,
                $ingredient->unit,
                $ingredient->food_portion_id,
            );
        }

        return $snapshots;
    }

    /** @return array<string, float|null> */
    public function totals(array $snapshots): array
    {
        $totals = array_fill_keys(NutritionCalculator::NUTRIENTS, 0.0);
        foreach ($snapshots as $snapshot) {
            foreach (NutritionCalculator::NUTRIENTS as $nutrient) {
                $value = $snapshot[$nutrient];
                $totals[$nutrient] = $totals[$nutrient] === null || $value === null
                    ? null : round($totals[$nutrient] + $value, 3);
            }
        }

        return $totals;
    }

    /** @return array<string, mixed> */
    private function snapshot(Food $food, float $quantity, string $unit, ?string $portionId): array
    {
        if ($food->is_archived || ! $food->nutrition) {
            throw ValidationException::withMessages(['items' => ['The food is unavailable for logging.']]);
        }
        $amount = $quantity;
        $inputUnit = $unit;
        $displayUnit = $unit;
        if ($unit === 'portion') {
            $portion = $food->portions->firstWhere('id', $portionId);
            if (! $portion) {
                throw ValidationException::withMessages(['items' => ['The portion does not belong to this food.']]);
            }
            $amount *= (float) $portion->base_amount;
            $unit = $portion->base_unit;
            $displayUnit = $portion->label;
        }
        $nutrition = $this->calculator->calculate($food->nutrition, $amount, $unit);

        return [
            'food_id' => $food->id, 'food_name' => $food->name, 'brand' => $food->brand,
            'quantity' => round($quantity, 3), 'unit' => $displayUnit,
            'input_unit' => $inputUnit,
            'food_portion_id' => $portionId,
            'nutrition_snapshot' => $food->nutrition->only(['basis_amount', 'basis_unit', ...NutritionCalculator::NUTRIENTS]),
            ...$nutrition,
        ];
    }
}
