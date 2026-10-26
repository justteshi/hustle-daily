<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;

class DayNutrition
{
    public function totals(Collection $entries): array
    {
        $totals = array_fill_keys(NutritionCalculator::NUTRIENTS, 0.0);
        foreach ($entries as $entry) {
            foreach (NutritionCalculator::NUTRIENTS as $nutrient) {
                $value = $entry->{$nutrient};
                $totals[$nutrient] = $totals[$nutrient] === null || $value === null
                    ? null : round($totals[$nutrient] + (float) $value, 3);
            }
        }
        if ($entries->isEmpty()) {
            foreach (['fibre', 'sugar', 'salt'] as $nutrient) {
                $totals[$nutrient] = null;
            }
        }

        return $totals;
    }
}
