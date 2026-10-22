<?php

namespace App\Services;

use App\Models\FoodNutrition;
use Illuminate\Validation\ValidationException;

class NutritionCalculator
{
    public const NUTRIENTS = ['calories', 'protein', 'carbohydrates', 'fat', 'fibre', 'sugar', 'salt'];

    /** @return array<string, float|null> */
    public function calculate(FoodNutrition $nutrition, float $amount, string $unit): array
    {
        if ($unit !== $nutrition->basis_unit) {
            throw ValidationException::withMessages(['unit' => ['No explicit conversion exists for this unit.']]);
        }

        $result = [];
        foreach (self::NUTRIENTS as $nutrient) {
            $value = $nutrition->{$nutrient};
            $result[$nutrient] = $value === null ? null : round((float) $value * $amount / (float) $nutrition->basis_amount, 3);
        }

        return $result;
    }
}
