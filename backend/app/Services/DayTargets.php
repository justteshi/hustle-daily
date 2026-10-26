<?php

namespace App\Services;

use App\Models\DayType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DayTargets
{
    public function ensureDefaults(User $user): void
    {
        DB::transaction(function () use ($user): void {
            foreach ([
                'training' => ['Training Day', '#2F6B4F', 0, 2000, 160, 10000],
                'rest' => ['Rest Day', '#2D5C91', 1, 3000, 120, null],
            ] as $key => [$name, $color, $order, $calories, $protein, $steps]) {
                $type = $user->dayTypes()->firstOrCreate(['key' => $key], [
                    'name' => $name, 'color' => $color, 'sort_order' => $order,
                ]);
                if ($type->targetProfile()->exists()) {
                    continue;
                }
                $profile = $type->targetProfile()->create();
                foreach ([
                    ['consumed_calories', $calories, 'kcal', 'budget'],
                    ['protein_g', $protein, 'g', 'minimum'],
                    ...($steps === null ? [] : [['steps', $steps, 'steps', 'minimum']]),
                ] as [$metric, $value, $unit, $rule]) {
                    $profile->values()->create([
                        'metric_code' => $metric, 'target_value' => $value,
                        'unit' => $unit, 'comparison_rule' => $rule,
                    ]);
                }
            }
        });
    }

    public function forDate(User $user, string $date): DayType
    {
        $this->ensureDefaults($user);
        $assignment = $user->dayAssignments()->whereDate('local_date', $date)->first();

        return $user->dayTypes()->with('targetProfile.values')
            ->findOrFail($assignment?->day_type_id ?? $user->dayTypes()->where('key', 'rest')->value('id'));
    }

    public function serialize(DayType $type): array
    {
        return [
            'id' => $type->id,
            'key' => $type->key,
            'name' => $type->name,
            'color' => $type->color,
            'is_active' => $type->is_active,
            'targets' => $type->targetProfile->values->mapWithKeys(fn ($value) => [
                $value->metric_code => [
                    'value' => (float) $value->target_value,
                    'unit' => $value->unit,
                    'comparison_rule' => $value->comparison_rule,
                ],
            ])->all(),
        ];
    }
}
