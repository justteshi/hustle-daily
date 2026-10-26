<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaySummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/days/2026-10-25')->assertUnauthorized();
    }

    public function test_returns_404_for_invalid_date(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/days/2026-02-30')->assertNotFound();
    }

    public function test_past_day_keeps_effective_targets_and_recalculates_corrected_food(): void
    {
        $this->travelTo('2026-10-26 10:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $type = $this->actingAs($user)->getJson('/api/v1/day-types')->json('data.0');
        $this->actingAs($user)->patchJson('/api/v1/days/2026-10-25', [
            'day_type_id' => $type['id'],
        ])->assertOk();
        $this->actingAs($user)->putJson('/api/v1/day-types/'.$type['id'], [
            'targets' => ['consumed_calories' => 2300, 'protein_g' => 175],
        ])->assertOk();
        $category = $user->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 0]);
        $otherCategory = $other->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 0]);
        $meal = $user->consumptions()->create([
            'meal_category_id' => $category->id, 'local_date' => '2026-10-25',
            'eaten_at' => '2026-10-25T08:00:00Z', 'idempotency_key' => 'day-summary-user',
            'request_fingerprint' => str_repeat('a', 64), 'calories' => 120, 'protein' => 20,
            'carbohydrates' => 8, 'fat' => 2,
        ]);
        $other->consumptions()->create([
            'meal_category_id' => $otherCategory->id, 'local_date' => '2026-10-25',
            'eaten_at' => '2026-10-25T08:00:00Z', 'idempotency_key' => 'day-summary-other',
            'request_fingerprint' => str_repeat('b', 64), 'calories' => 999, 'protein' => 99,
            'carbohydrates' => 80, 'fat' => 30,
        ]);

        $this->actingAs($user)->getJson('/api/v1/days/2026-10-25')->assertOk()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.day_type.key', 'training')
            ->assertJsonPath('data.day_type.targets.consumed_calories.value', 2000)
            ->assertJsonPath('data.consumed.calories', 120)
            ->assertJsonPath('data.consumed.protein', 20)
            ->assertJsonCount(1, 'data.consumptions')
            ->assertJsonPath('data.health', null);

        $meal->update(['calories' => 170, 'protein' => 23.5]);

        $this->actingAs($user)->getJson('/api/v1/days/2026-10-25')->assertOk()
            ->assertJsonPath('data.consumed.calories', 170)
            ->assertJsonPath('data.consumed.protein', 23.5);
    }

    public function test_empty_day_has_default_type_and_no_health_data(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/days/2026-10-25')->assertOk()
            ->assertJsonPath('data.source', 'default')
            ->assertJsonPath('data.day_type.key', 'rest')
            ->assertJsonPath('data.consumed.calories', 0)
            ->assertJsonPath('data.consumed.fibre', null)
            ->assertJsonCount(0, 'data.consumptions')
            ->assertJsonPath('data.health', null);
    }

    public function test_conclude_reopen_and_reconclude_preserve_audit_history_and_idempotency(): void
    {
        $this->travelTo('2026-10-26 10:00:00');
        $user = User::factory()->create();
        $headers = ['Idempotency-Key' => 'conclude-once'];
        $url = '/api/v1/days/2026-10-25';

        $this->actingAs($user)->postJson($url.'/conclude', [], $headers)->assertUnprocessable();
        $this->actingAs($user)->postJson($url.'/conclude', ['acknowledge_missing_health' => true], $headers)
            ->assertOk()->assertJsonPath('data.status', 'concluded')
            ->assertJsonPath('data.last_conclusion.health_available', false)
            ->assertJsonCount(1, 'data.revisions');
        $this->actingAs($user)->postJson($url.'/conclude', ['acknowledge_missing_health' => true], $headers)
            ->assertOk()->assertJsonCount(1, 'data.revisions');
        $this->actingAs($user)->patchJson($url, ['day_type_id' => $user->dayTypes()->firstOrFail()->id])
            ->assertConflict();
        $this->actingAs($user)->postJson($url.'/reopen', [], ['Idempotency-Key' => 'reopen-once'])
            ->assertOk()->assertJsonPath('data.status', 'reopened')
            ->assertJsonCount(2, 'data.revisions');
        $this->actingAs($user)->postJson($url.'/conclude', ['acknowledge_missing_health' => true], ['Idempotency-Key' => 'reconclude-once'])
            ->assertOk()->assertJsonPath('data.status', 'concluded')
            ->assertJsonPath('data.revisions.2.action', 'reconclude');
        $this->actingAs($user)->getJson($url)->assertOk()
            ->assertJsonPath('data.review.status', 'concluded')
            ->assertJsonCount(3, 'data.review.revisions');
    }

    public function test_review_is_private_and_future_days_cannot_be_concluded(): void
    {
        $this->travelTo('2026-10-26 10:00:00');
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->postJson('/api/v1/days/2026-10-27/conclude', [
            'acknowledge_missing_health' => true,
        ], ['Idempotency-Key' => 'future'])->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/v1/days/2026-10-25/conclude', [
            'acknowledge_missing_health' => true,
        ], ['Idempotency-Key' => 'owner'])->assertOk();
        $this->actingAs($other)->getJson('/api/v1/days/2026-10-25')->assertOk()
            ->assertJsonPath('data.review.status', 'open')
            ->assertJsonCount(0, 'data.review.revisions');
    }

    public function test_concluded_day_blocks_meal_deletion_until_reopened(): void
    {
        $this->travelTo('2026-10-26 10:00:00');
        $user = User::factory()->create();
        $category = $user->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 0]);
        $meal = $user->consumptions()->create([
            'meal_category_id' => $category->id,
            'local_date' => '2026-10-25',
            'eaten_at' => '2026-10-25T08:00:00Z',
            'idempotency_key' => 'initial-meal',
            'request_fingerprint' => str_repeat('a', 64),
            'calories' => 120, 'protein' => 20, 'carbohydrates' => 8, 'fat' => 2,
        ]);
        $dateUrl = '/api/v1/days/2026-10-25';
        $this->actingAs($user)->postJson($dateUrl.'/conclude', [
            'acknowledge_missing_health' => true,
        ], ['Idempotency-Key' => 'conclude-meal'])->assertOk();

        $this->actingAs($user)->deleteJson('/api/v1/consumptions/'.$meal->id, [], [
            'Idempotency-Key' => 'delete-meal',
        ])->assertConflict();
        $this->assertNotSoftDeleted($meal);

        $this->actingAs($user)->postJson($dateUrl.'/reopen', [], [
            'Idempotency-Key' => 'reopen-meal',
        ])->assertOk();
        $this->actingAs($user)->deleteJson('/api/v1/consumptions/'.$meal->id, [], [
            'Idempotency-Key' => 'delete-meal',
        ])->assertNoContent();
        $this->assertSoftDeleted($meal);
        $this->actingAs($user)->getJson($dateUrl)->assertJsonPath('data.consumed.calories', 0);
    }
}
