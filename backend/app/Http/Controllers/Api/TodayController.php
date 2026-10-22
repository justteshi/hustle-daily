<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NutritionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $timeZone = $request->user()->settings?->time_zone ?? 'Europe/Sofia';
        $localDate = now($timeZone)->toDateString();
        $entries = $request->user()->consumptions()->with('items')
            ->whereDate('local_date', $localDate)->orderBy('eaten_at')->orderBy('id')->get();
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

        return response()->json(['data' => [
            'local_date' => $localDate,
            'time_zone' => $timeZone,
            'consumed' => $totals,
            'consumptions' => $entries,
            'health' => null,
        ]]);
    }
}
