<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoAcceptanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_breakfast_recipe_can_be_logged_corrected_and_retried_without_duplicate_totals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22T12:00:00+03:00'));
        $user = User::factory()->create();
        $user->settings()->create(['time_zone' => 'Europe/Sofia']);
        $this->actingAs($user);
        $category = $this->getJson('/api/v1/meal-categories')->assertOk()->json('data.0.id');
        $skyr = $this->withHeader('Idempotency-Key', 'create-skyr')->postJson('/api/v1/foods', [
            'name' => 'Skyr', 'brand' => 'Brand A',
            'nutrition' => ['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0],
        ])->assertCreated()->json('data.id');
        $milk = $this->withHeader('Idempotency-Key', 'create-milk')->postJson('/api/v1/foods', [
            'name' => 'Milk', 'brand' => 'Brand B',
            'nutrition' => ['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2],
        ])->assertCreated()->json('data.id');
        $recipe = $this->withHeader('Idempotency-Key', 'create-breakfast')->postJson('/api/v1/recipes', [
            'name' => 'Breakfast bowl', 'yield_servings' => 1,
            'ingredients' => [['food_id' => $skyr, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');
        $log = [
            'meal_category_id' => $category, 'eaten_at' => '2026-10-22T08:00:00+03:00',
            'recipe_id' => $recipe, 'servings' => 1,
        ];
        $meal = $this->withHeader('Idempotency-Key', 'log-breakfast')->postJson('/api/v1/consumptions', $log)
            ->assertCreated()->json('data.id');
        $this->withHeader('Idempotency-Key', 'log-breakfast')->postJson('/api/v1/consumptions', $log)->assertOk();
        $this->assertDatabaseCount('consumptions', 1);
        $this->getJson('/api/v1/dashboard/today')->assertOk()
            ->assertJsonPath('data.consumed.calories', 60)
            ->assertJsonPath('data.consumed.protein', 11);

        $correction = ['meal_category_id' => $category, 'items' => [
            ['food_id' => $skyr, 'quantity' => 100, 'unit' => 'g'],
            ['food_id' => $milk, 'quantity' => 80, 'unit' => 'ml'],
        ]];
        $this->withHeader('Idempotency-Key', 'add-forgotten-milk')->patchJson('/api/v1/consumptions/'.$meal, $correction)->assertOk();
        $this->withHeader('Idempotency-Key', 'add-forgotten-milk')->patchJson('/api/v1/consumptions/'.$meal, $correction)->assertOk();

        $this->assertDatabaseCount('consumptions', 1);
        $this->assertDatabaseCount('consumption_revisions', 1);
        $this->getJson('/api/v1/dashboard/today')->assertOk()
            ->assertJsonPath('data.consumed.calories', 100)
            ->assertJsonPath('data.consumed.protein', 13.4)
            ->assertJsonCount(1, 'data.consumptions');
    }
}
