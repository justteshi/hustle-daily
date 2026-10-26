<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DayTargets;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DayTypeController extends Controller
{
    public function index(Request $request, DayTargets $targets): JsonResponse
    {
        $targets->ensureDefaults($request->user());
        $date = now($request->user()->settings?->time_zone ?? 'Europe/Sofia')->toDateString();

        return response()->json(['data' => $request->user()->dayTypes()
            ->orderBy('sort_order')->get()
            ->map(fn ($type) => $targets->serialize($type, $date))]);
    }

    public function update(Request $request, string $dayType, DayTargets $targets): JsonResponse
    {
        $targets->ensureDefaults($request->user());
        $type = $request->user()->dayTypes()->findOrFail($dayType);
        $date = now($request->user()->settings?->time_zone ?? 'Europe/Sofia')->toDateString();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'targets' => ['required', 'array:consumed_calories,protein_g,steps'],
            'targets.consumed_calories' => ['required', 'numeric', 'gt:0', 'lte:100000'],
            'targets.protein_g' => ['required', 'numeric', 'gt:0', 'lte:10000'],
            'targets.steps' => ['sometimes', 'nullable', 'integer', 'gt:0', 'lte:1000000'],
        ]);
        DB::transaction(function () use ($request, $type, $data, $date, $targets): void {
            $request->user()->dayTypes()->whereKey($type->id)->lockForUpdate()->firstOrFail();
            $current = $targets->profileForDate($type, $date);
            if ($current->effective_from !== $date) {
                $current->update(['effective_to' => CarbonImmutable::parse($date)->subDay()->toDateString()]);
                $profile = $type->targetProfiles()->create(['effective_from' => $date]);
                foreach ($current->values as $value) {
                    $profile->values()->create($value->only(['metric_code', 'target_value', 'unit', 'comparison_rule']));
                }
            } else {
                $profile = $current;
            }
            $type->update(collect($data)->only(['name', 'color'])->all());
            foreach (['consumed_calories' => ['kcal', 'budget'], 'protein_g' => ['g', 'minimum'], 'steps' => ['steps', 'minimum']] as $metric => [$unit, $rule]) {
                if (! array_key_exists($metric, $data['targets'])) {
                    continue;
                }
                if ($data['targets'][$metric] === null) {
                    $profile->values()->where('metric_code', $metric)->delete();

                    continue;
                }
                $profile->values()->updateOrCreate(['metric_code' => $metric], [
                    'target_value' => $data['targets'][$metric], 'unit' => $unit, 'comparison_rule' => $rule,
                ]);
            }
        });

        return response()->json(['data' => $targets->serialize($type->fresh(), $date)]);
    }
}
