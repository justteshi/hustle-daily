<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DayNutrition;
use App\Services\DayTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    /**
     * Show today's food totals and logged meals.
     *
     * Today is based on the user's time zone. This phase reports consumed nutrition only; health data is not yet synchronized.
     */
    public function __invoke(Request $request, DayTargets $targets, DayNutrition $nutrition): JsonResponse
    {
        $timeZone = $request->user()->settings?->time_zone ?? 'Europe/Sofia';
        $localDate = now($timeZone)->toDateString();
        $dayType = $targets->forDate($request->user(), $localDate);
        $entries = $request->user()->consumptions()->with('items')
            ->whereDate('local_date', $localDate)->orderBy('eaten_at')->orderBy('id')->get();
        $reviewStatus = $request->user()->dailySummaries()->whereDate('local_date', $localDate)->value('status') ?? 'open';

        return response()->json(['data' => [
            'local_date' => $localDate,
            'time_zone' => $timeZone,
            'day_type' => $targets->serialize($dayType, $localDate),
            'consumed' => $nutrition->totals($entries),
            'consumptions' => $entries,
            'health' => null,
            'review_status' => $reviewStatus,
        ]]);
    }
}
