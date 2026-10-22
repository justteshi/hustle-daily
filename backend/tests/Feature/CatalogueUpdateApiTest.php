<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_food_update_preserves_logged_snapshot_and_portion_ids_on_retry(): void
    {
        $user = User::factory()->create();
        $category = $user->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $food = $user->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $portion = $food->portions()->create(['label' => 'scoop', 'base_amount' => 30, 'base_unit' => 'g']);
        $this->actingAs($user);
        $this->withHeader('Idempotency-Key', 'logged-skyr')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-22T08:00:00+03:00',
            'items' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated();
        $payload = [
            'name' => 'Skyr', 'brand' => 'Brand B', 'nutrition' => [
                'basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 70, 'protein' => 12, 'carbohydrates' => 4, 'fat' => 0,
            ],
            'portions' => [
                ['id' => $portion->id, 'label' => 'scoop', 'base_amount' => 35, 'base_unit' => 'g'],
                ['label' => 'cup', 'base_amount' => 150, 'base_unit' => 'g'],
            ],
        ];
        $this->withHeader('Idempotency-Key', 'edit-skyr')->patchJson('/api/v1/foods/'.$food->id, $payload)
            ->assertOk()->assertJsonPath('data.brand', 'Brand B');
        $this->withHeader('Idempotency-Key', 'edit-skyr')->patchJson('/api/v1/foods/'.$food->id, $payload)->assertOk();
        $this->assertDatabaseCount('catalog_mutation_keys', 1);
        $this->assertDatabaseCount('food_portions', 2);
        $this->assertDatabaseHas('food_portions', ['id' => $portion->id, 'base_amount' => 35]);
        $this->getJson('/api/v1/consumptions?date=2026-10-22')->assertOk()->assertJsonPath('data.0.calories', '60.000');
        $this->withHeader('Idempotency-Key', 'edit-skyr')->patchJson('/api/v1/foods/'.$food->id, [...$payload, 'brand' => 'C'])->assertStatus(409);
    }

    public function test_recipe_update_keeps_old_log_and_rejects_foreign_ingredients(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $skyr = $owner->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $skyr->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $milk = $owner->foods()->create(['name' => 'Milk', 'brand' => 'B']);
        $milk->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2]);
        $foreign = $other->foods()->create(['name' => 'Private', 'brand' => 'C']);
        $foreign->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 99, 'protein' => 3, 'carbohydrates' => 4, 'fat' => 2]);
        $recipe = $owner->recipes()->create(['name' => 'Breakfast', 'yield_servings' => 1]);
        $recipe->ingredients()->create(['food_id' => $skyr->id, 'quantity' => 100, 'unit' => 'g', 'sort_order' => 0]);
        $this->actingAs($owner);
        $this->withHeader('Idempotency-Key', 'old-breakfast')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-22T08:00:00+03:00', 'recipe_id' => $recipe->id, 'servings' => 1,
        ])->assertCreated()->assertJsonPath('data.calories', 60);
        $payload = ['name' => 'Breakfast with milk', 'yield_servings' => 1, 'ingredients' => [
            ['food_id' => $skyr->id, 'quantity' => 100, 'unit' => 'g'],
            ['food_id' => $milk->id, 'quantity' => 80, 'unit' => 'ml'],
        ]];
        $this->withHeader('Idempotency-Key', 'edit-recipe')->patchJson('/api/v1/recipes/'.$recipe->id, $payload)
            ->assertOk()->assertJsonPath('data.name', 'Breakfast with milk');
        $this->withHeader('Idempotency-Key', 'edit-recipe')->patchJson('/api/v1/recipes/'.$recipe->id, $payload)->assertOk();
        $this->assertDatabaseCount('recipe_ingredients', 2);
        $this->assertDatabaseCount('catalog_mutation_keys', 1);
        $this->getJson('/api/v1/consumptions?date=2026-10-22')->assertOk()->assertJsonPath('data.0.calories', '60.000');
        $this->withHeader('Idempotency-Key', 'foreign-ingredient')->patchJson('/api/v1/recipes/'.$recipe->id, [
            ...$payload, 'ingredients' => [['food_id' => $foreign->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertNotFound();
        $this->assertDatabaseCount('recipe_ingredients', 2);
        $this->actingAs($other);
        $this->withHeader('Idempotency-Key', 'foreign-food-edit')->patchJson('/api/v1/foods/'.$skyr->id, [])->assertNotFound();
        $this->withHeader('Idempotency-Key', 'foreign-recipe-edit')->patchJson('/api/v1/recipes/'.$recipe->id, $payload)->assertNotFound();
    }
}
