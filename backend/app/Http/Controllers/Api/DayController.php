<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DayNutrition;
use App\Services\DayTargets;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DayController extends Controller
{
    public function show(Request $request, string $date, DayTargets $targets, DayNutrition $nutrition): JsonResponse
    {
        $this->validateDate($date);
        $user = $request->user();
        $type = $targets->forDate($user, $date);
        $entries = $user->consumptions()->with('items')
            ->whereDate('local_date', $date)->orderBy('eaten_at')->orderBy('id')->get();
        $assignment = $user->dayAssignments()->whereDate('local_date', $date)->first();
        $review = $user->dailySummaries()->with('revisions')->whereDate('local_date', $date)->first();

        return response()->json(['data' => [
            'local_date' => $date,
            'source' => $assignment?->source ?? 'default',
            'day_type' => $targets->serialize($type, $date),
            'consumed' => $nutrition->totals($entries),
            'consumptions' => $entries,
            'health' => null,
            'review' => $this->reviewData($review),
        ]]);
    }

    public function update(Request $request, string $date, DayTargets $targets): JsonResponse
    {
        $this->validateDate($date);
        $data = $request->validate(['day_type_id' => ['required', 'uuid']]);
        $targets->ensureDefaults($request->user());
        [$type, $assignment] = DB::transaction(function () use ($request, $date, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->ensureEditable($request->user(), $date);
            $type = $request->user()->dayTypes()->findOrFail($data['day_type_id']);
            $assignment = $request->user()->dayAssignments()->updateOrCreate(['local_date' => $date], [
                'day_type_id' => $type->id, 'source' => 'manual',
            ]);

            return [$type, $assignment];
        });

        return response()->json(['data' => [
            'local_date' => $date,
            'source' => $assignment->source,
            'day_type' => $targets->serialize($type, $date),
        ]]);
    }

    public function conclude(Request $request, string $date, DayTargets $targets, DayNutrition $nutrition): JsonResponse
    {
        $this->validateDate($date);
        $request->validate(['acknowledge_missing_health' => ['required', 'accepted']]);
        $today = CarbonImmutable::now($request->user()->settings?->time_zone ?? 'Europe/Sofia')->toDateString();
        abort_if($date > $today, 422, 'A future day cannot be concluded.');

        return $this->transition($request, $date, 'conclude', $targets, $nutrition);
    }

    public function reopen(Request $request, string $date, DayTargets $targets, DayNutrition $nutrition): JsonResponse
    {
        $this->validateDate($date);

        return $this->transition($request, $date, 'reopen', $targets, $nutrition);
    }

    private function transition(Request $request, string $date, string $action, DayTargets $targets, DayNutrition $nutrition): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! $key || mb_strlen($key) > 120) {
            throw ValidationException::withMessages(['idempotency_key' => ['An Idempotency-Key header of at most 120 characters is required.']]);
        }
        $fingerprint = hash('sha256', json_encode([$date, $action, $action === 'conclude']));
        $user = $request->user();

        $review = DB::transaction(function () use ($user, $date, $action, $key, $fingerprint, $targets, $nutrition) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->dailySummaries()->whereHas('revisions', fn ($query) => $query->where('idempotency_key', $key))->first();
            if ($existing) {
                $revision = $existing->revisions()->where('idempotency_key', $key)->firstOrFail();
                abort_if($revision->request_fingerprint !== $fingerprint, 409, 'Idempotency key was already used with different data.');

                return $existing->load('revisions');
            }
            $review = $user->dailySummaries()->firstOrCreate(['local_date' => $date], ['status' => 'open']);
            $before = $this->reviewData($review);
            if ($action === 'conclude') {
                abort_unless(in_array($review->status, ['open', 'reopened'], true), 409, 'This day is already concluded.');
                $type = $targets->forDate($user, $date);
                $entries = $user->consumptions()->whereDate('local_date', $date)->get();
                $review->last_conclusion = [
                    'day_type' => $targets->serialize($type, $date),
                    'consumed' => $nutrition->totals($entries),
                    'health_available' => false,
                ];
                $review->status = 'concluded';
                $review->concluded_at = now()->utc();
            } else {
                abort_unless($review->status === 'concluded', 409, 'Only a concluded day can be reopened.');
                $review->status = 'reopened';
            }
            $review->save();
            $review->revisions()->create([
                'user_id' => $user->id,
                'actor_user_id' => $user->id,
                'action' => $action === 'conclude' && $before['status'] === 'reopened' ? 'reconclude' : $action,
                'before_state' => $before,
                'after_state' => $this->reviewData($review),
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
            ]);

            return $review->load('revisions');
        });

        return response()->json(['data' => $this->reviewData($review)]);
    }

    private function reviewData($review): array
    {
        return [
            'status' => $review?->status ?? 'open',
            'concluded_at' => $review?->concluded_at?->toIso8601String(),
            'last_conclusion' => $review?->last_conclusion,
            'revisions' => $review?->relationLoaded('revisions')
                ? $review->revisions->map(fn ($revision) => [
                    'action' => $revision->action,
                    'created_at' => $revision->created_at->toIso8601String(),
                ])->all() : [],
        ];
    }

    private function ensureEditable(User $user, string $date): void
    {
        abort_if($user->dailySummaries()->whereDate('local_date', $date)->where('status', 'concluded')->exists(), 409, 'Reopen this day before editing it.');
    }

    private function validateDate(string $date): void
    {
        $validDate = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        abort_unless($validDate, 404);
    }
}
