<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodayApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_uses_user_time_zone_and_only_their_food_entries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21T22:30:00Z'));
        $alice = User::factory()->create();
        $alice->settings()->create(['time_zone' => 'Europe/Sofia']);
        $bob = User::factory()->create();
        $category = $alice->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 0]);
        $bobCategory = $bob->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 0]);
        $alice->consumptions()->create(['meal_category_id' => $category->id, 'local_date' => '2026-10-22', 'eaten_at' => '2026-10-21T22:00:00Z', 'idempotency_key' => 'alice', 'request_fingerprint' => str_repeat('a', 64), 'calories' => 190, 'protein' => 22.5, 'carbohydrates' => 15, 'fat' => 4]);
        $bob->consumptions()->create(['meal_category_id' => $bobCategory->id, 'local_date' => '2026-10-22', 'eaten_at' => '2026-10-21T22:00:00Z', 'idempotency_key' => 'bob', 'request_fingerprint' => str_repeat('b', 64), 'calories' => 999, 'protein' => 99, 'carbohydrates' => 99, 'fat' => 99]);

        $this->actingAs($alice)->getJson('/api/v1/dashboard/today')->assertOk()
            ->assertJsonPath('data.local_date', '2026-10-22')
            ->assertJsonPath('data.consumed.calories', 190)
            ->assertJsonPath('data.consumed.protein', 22.5)
            ->assertJsonCount(1, 'data.consumptions')
            ->assertJsonPath('data.health', null);
    }

    public function test_today_has_zero_consumed_food_but_unknown_health_when_empty(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/dashboard/today')->assertOk()
            ->assertJsonPath('data.consumed.calories', 0)
            ->assertJsonPath('data.consumed.fibre', null)
            ->assertJsonPath('data.health', null);
    }
}
