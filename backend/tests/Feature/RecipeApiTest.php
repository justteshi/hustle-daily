<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_logs_branded_ingredient_snapshots_and_retries_safely(): void
    {
        $user = User::factory()->create();
        $user->settings()->create([]);
        $this->actingAs($user);
        $category = $this->getJson('/api/v1/meal-categories')->json('data.0.id');
        $milk = $user->foods()->create(['name' => 'Milk', 'brand' => 'Brand A']);
        $milk->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2]);
        $glass = $milk->portions()->create(['label' => 'glass', 'base_amount' => 200, 'base_unit' => 'ml']);
        $skyr = $user->foods()->create(['name' => 'Skyr', 'brand' => 'Brand B']);
        $skyr->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $recipePayload = [
            'name' => 'Breakfast bowl', 'yield_servings' => 2,
            'ingredients' => [
                ['food_id' => $milk->id, 'quantity' => 1, 'unit' => 'portion', 'portion_id' => $glass->id],
                ['food_id' => $skyr->id, 'quantity' => 100, 'unit' => 'g'],
            ],
        ];

        $recipeId = $this->withHeader('Idempotency-Key', 'recipe-breakfast')->postJson('/api/v1/recipes', $recipePayload)
            ->assertCreated()->json('data.id');
        $this->withHeader('Idempotency-Key', 'recipe-breakfast')->postJson('/api/v1/recipes', $recipePayload)->assertOk();
        $this->assertDatabaseCount('recipes', 1);
        $payload = ['meal_category_id' => $category, 'eaten_at' => '2026-10-22T08:00:00+03:00', 'recipe_id' => $recipeId, 'servings' => 1];
        $this->withHeader('Idempotency-Key', 'log-breakfast')->postJson('/api/v1/consumptions', $payload)
            ->assertCreated()->assertJsonPath('data.calories', 80)->assertJsonPath('data.protein', 8.5)
            ->assertJsonPath('data.source_name', 'Breakfast bowl');
        $this->withHeader('Idempotency-Key', 'log-breakfast')->postJson('/api/v1/consumptions', $payload)->assertOk();
        $this->assertDatabaseCount('consumptions', 1);
        $this->assertDatabaseCount('consumption_items', 2);
        $this->assertDatabaseHas('consumption_items', ['food_id' => $milk->id, 'input_unit' => 'portion', 'food_portion_id' => $glass->id]);
        $milk->nutrition()->update(['calories' => 99]);
        $this->getJson('/api/v1/consumptions?date=2026-10-22')->assertOk()->assertJsonPath('data.0.calories', '80.000');
    }

    public function test_recipe_cannot_reference_another_users_food_or_be_viewed_by_them(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobFood = $bob->foods()->create(['name' => 'Private', 'brand' => 'Brand B']);
        $bobFood->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $this->actingAs($alice);

        $this->withHeader('Idempotency-Key', 'cross-user-recipe')->postJson('/api/v1/recipes', [
            'name' => 'Forbidden', 'yield_servings' => 1,
            'ingredients' => [['food_id' => $bobFood->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertNotFound();
        $this->assertDatabaseCount('recipes', 0);
        $recipe = $bob->recipes()->create(['name' => 'Private recipe', 'yield_servings' => 1, 'idempotency_key' => 'bob-key', 'request_fingerprint' => str_repeat('a', 64)]);
        $this->getJson('/api/v1/recipes/'.$recipe->id)->assertNotFound();
        $this->getJson('/api/v1/recipes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_recipe_rejects_missing_explicit_conversion(): void
    {
        $user = User::factory()->create();
        $food = $user->foods()->create(['name' => 'Milk', 'brand' => 'Brand A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2]);
        $this->actingAs($user);

        $this->withHeader('Idempotency-Key', 'wrong-unit')->postJson('/api/v1/recipes', [
            'name' => 'Impossible', 'yield_servings' => 1,
            'ingredients' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        $this->assertDatabaseCount('recipes', 0);
    }
}
