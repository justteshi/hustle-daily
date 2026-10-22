<?php

namespace App\Services;

use App\Models\Consumption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CorrectConsumption
{
    public function __construct(private ConsumptionSnapshots $snapshots) {}

    /** @param array<string, mixed> $input */
    public function handle(Consumption $consumption, User $actor, array $input, ?string $idempotencyKey = null, ?string $requestFingerprint = null): Consumption
    {
        abort_unless($actor->is_admin || $actor->id === $consumption->user_id, 403);

        $input['reason'] = trim((string) ($input['reason'] ?? ''));

        $data = Validator::make($input, [
            'meal_category_id' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'correction_items' => ['sometimes', 'array', 'max:100'],
            'correction_items.*.food_id' => ['required', 'uuid'],
            'correction_items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'correction_items.*.unit' => ['required', 'in:g,ml,serving,portion'],
            'correction_items.*.portion_id' => ['required_if:correction_items.*.unit,portion', 'nullable', 'uuid'],
        ])->validate();

        return DB::transaction(function () use ($consumption, $actor, $data, $idempotencyKey, $requestFingerprint): Consumption {
            $record = Consumption::with('items')->lockForUpdate()->findOrFail($consumption->id);
            if ($idempotencyKey !== null) {
                $existing = $record->revisions()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    abort_if($existing->request_fingerprint !== $requestFingerprint, 409, 'Idempotency key was already used with different data.');

                    return $record;
                }
            }
            $owner = User::with('settings')->findOrFail($record->user_id);
            if (! $owner->mealCategories()->where('is_active', true)->whereKey($data['meal_category_id'])->exists()) {
                throw ValidationException::withMessages(['meal_category_id' => 'Choose an active category belonging to this user.']);
            }

            $before = $this->state($record);
            $record->meal_category_id = $data['meal_category_id'];
            $record->notes = $data['notes'] ?? null;
            if (! empty($data['correction_items'])) {
                $items = array_map(fn (array $item): array => $this->snapshots->food($owner, $item), $data['correction_items']);
                $record->fill([
                    ...$this->snapshots->totals($items),
                    'source_type' => 'foods', 'source_id' => null, 'source_name' => null,
                    'source_quantity' => null, 'source_unit' => null,
                ]);
                $record->items()->delete();
                $record->items()->createMany($items);
            }
            $record->save();
            $record->refresh()->load('items');
            $after = $this->state($record);
            if ($before === $after) {
                throw ValidationException::withMessages(['reason' => 'Make a change before saving a correction.']);
            }
            $record->revisions()->create([
                'user_id' => $owner->id, 'actor_user_id' => $actor->id,
                'revision_number' => ((int) $record->revisions()->max('revision_number')) + 1,
                'reason' => $data['reason'], 'before_state' => $before, 'after_state' => $after,
                'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $requestFingerprint,
                'created_at' => now()->utc(),
            ]);

            return $record;
        });
    }

    /** @return array<string, mixed> */
    private function state(Consumption $record): array
    {
        return [
            ...$record->only(['user_id', 'meal_category_id', 'local_date', 'eaten_at', 'notes', 'source_type', 'source_id', 'source_name', 'source_quantity', 'source_unit', 'calories', 'protein', 'carbohydrates', 'fat', 'fibre', 'sugar', 'salt']),
            'items' => $record->items->map(fn ($item): array => $item->only(['food_id', 'food_name', 'brand', 'quantity', 'unit', 'input_unit', 'food_portion_id', 'nutrition_snapshot', 'calories', 'protein', 'carbohydrates', 'fat', 'fibre', 'sugar', 'salt']))->all(),
        ];
    }
}
