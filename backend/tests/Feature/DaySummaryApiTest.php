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
}
