<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_lists_only_the_users_dates_and_past_meals_remain_editable(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $food = $owner->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $otherCategory = $other->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $otherFood = $other->foods()->create(['name' => 'Private', 'brand' => 'B']);
        $otherFood->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 99, 'protein' => 3, 'carbohydrates' => 4, 'fat' => 2]);
        $this->actingAs($other);
        $this->withHeader('Idempotency-Key', 'other-day')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $otherCategory->id, 'eaten_at' => '2026-10-21T08:00:00+03:00',
            'items' => [['food_id' => $otherFood->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated();
        $this->actingAs($owner);
        $id = $this->withHeader('Idempotency-Key', 'owner-day')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-20T08:00:00+03:00',
            'items' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/consumptions/dates')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.local_date', '2026-10-20')
            ->assertJsonPath('data.0.meal_count', 1);
        $this->getJson('/api/v1/consumptions?date=2026-10-20')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->withHeader('Idempotency-Key', 'edit-old-meal')->patchJson('/api/v1/consumptions/'.$id, [
            'meal_category_id' => $category->id, 'notes' => 'Corrected from history',
        ])->assertOk()->assertJsonPath('data.notes', 'Corrected from history');
        $this->assertDatabaseHas('consumptions', ['id' => $id, 'local_date' => '2026-10-20', 'calories' => 60]);
        $this->assertDatabaseCount('consumption_revisions', 1);

        $this->getJson('/api/v1/consumptions?date=2026-10-21')->assertOk()->assertJsonCount(0, 'data');
    }
}
