<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodLoggingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_branded_foods_log_immutable_totals_and_retry_without_duplicates(): void
    {
        $user = User::factory()->create();
        $user->settings()->create([]);
        $this->actingAs($user);
        $category = $this->getJson('/api/v1/meal-categories')->assertOk()->json('data.0.id');

        $milk = $this->withHeader('Idempotency-Key', 'milk-create')->postJson('/api/v1/foods', [
            'name' => 'Milk', 'brand' => 'Brand A',
            'nutrition' => ['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2],
            'portions' => [['label' => 'glass', 'base_amount' => 200, 'base_unit' => 'ml']],
        ])->assertCreated()->json('data.id');
        $skyr = $this->withHeader('Idempotency-Key', 'skyr-create')->postJson('/api/v1/foods', [
            'name' => 'Skyr', 'brand' => 'Brand B',
            'nutrition' => ['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0],
        ])->assertCreated()->json('data.id');
        $portion = $user->foods()->findOrFail($milk)->portions()->firstOrFail();
        $payload = [
            'meal_category_id' => $category, 'eaten_at' => '2026-10-22T08:00:00+03:00',
            'items' => [
                ['food_id' => $milk, 'quantity' => 1, 'unit' => 'portion', 'portion_id' => $portion->id],
                ['food_id' => $skyr, 'quantity' => 150, 'unit' => 'g'],
            ],
        ];
        $this->withHeader('Idempotency-Key', 'breakfast-1')->postJson('/api/v1/consumptions', $payload)
            ->assertCreated()->assertJsonPath('data.calories', 190)->assertJsonPath('data.protein', 22.5)
            ->assertJsonPath('data.local_date', '2026-10-22');
        $this->withHeader('Idempotency-Key', 'breakfast-1')->postJson('/api/v1/consumptions', $payload)->assertOk();
        $this->assertDatabaseCount('consumptions', 1);
        $this->assertDatabaseCount('consumption_items', 2);
        $user->foods()->findOrFail($milk)->nutrition()->update(['calories' => 80]);
        $this->getJson('/api/v1/consumptions?date=2026-10-22')->assertOk()
            ->assertJsonPath('data.0.calories', '190.000');
    }

    public function test_food_and_category_are_isolated_by_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobFood = $bob->foods()->create(['name' => 'Milk', 'brand' => 'Private']);
        $bobFood->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2]);
        $bobCategory = $bob->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 0]);
        $this->actingAs($alice);
        $this->getJson('/api/v1/foods/'.$bobFood->id)->assertNotFound();
        $this->getJson('/api/v1/foods?query=Milk')->assertOk()->assertJsonCount(0, 'data');
        $this->withHeader('Idempotency-Key', 'cross-user')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $bobCategory->id, 'eaten_at' => '2026-10-22T08:00:00+03:00',
            'items' => [['food_id' => $bobFood->id, 'quantity' => 100, 'unit' => 'ml']],
        ])->assertNotFound();
        $this->assertDatabaseCount('consumptions', 0);
    }

    public function test_rejects_uninvented_density_conversion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withHeader('Idempotency-Key', 'bad-portion')->postJson('/api/v1/foods', [
            'name' => 'Milk', 'brand' => 'Brand A',
            'nutrition' => ['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2],
            'portions' => [['label' => 'spoon', 'base_amount' => 15, 'base_unit' => 'g']],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('foods', 0);
    }
}
