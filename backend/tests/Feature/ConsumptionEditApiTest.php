<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionEditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_adds_forgotten_milk_with_an_audited_idempotent_edit(): void
    {
        $user = User::factory()->create();
        $category = $user->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $skyr = $user->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $skyr->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $milk = $user->foods()->create(['name' => 'Milk', 'brand' => 'B']);
        $milk->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2]);
        $this->actingAs($user);
        $id = $this->withHeader('Idempotency-Key', 'breakfast')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-22T08:00:00+03:00',
            'items' => [['food_id' => $skyr->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');
        $payload = ['meal_category_id' => $category->id, 'items' => [
            ['food_id' => $skyr->id, 'quantity' => 100, 'unit' => 'g'],
            ['food_id' => $milk->id, 'quantity' => 80, 'unit' => 'ml'],
        ]];

        $this->withHeader('Idempotency-Key', 'edit-breakfast')->patchJson('/api/v1/consumptions/'.$id, $payload)
            ->assertOk()->assertJsonPath('data.calories', '100.000');
        $this->withHeader('Idempotency-Key', 'edit-breakfast')->patchJson('/api/v1/consumptions/'.$id, $payload)->assertOk();
        $this->assertDatabaseCount('consumption_revisions', 1);
        $this->assertDatabaseHas('consumptions', ['id' => $id, 'calories' => 100]);
        $this->assertDatabaseCount('consumption_items', 2);
        $this->withHeader('Idempotency-Key', 'edit-breakfast')->patchJson('/api/v1/consumptions/'.$id, ['meal_category_id' => $category->id])->assertStatus(409);
    }

    public function test_other_user_cannot_edit_meal(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $food = $owner->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $this->actingAs($owner);
        $id = $this->withHeader('Idempotency-Key', 'private-breakfast')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-22T08:00:00+03:00',
            'items' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');
        $this->actingAs($other);
        $this->withHeader('Idempotency-Key', 'foreign-edit')->patchJson('/api/v1/consumptions/'.$id, ['meal_category_id' => $category->id])->assertNotFound();
        $this->assertDatabaseCount('consumption_revisions', 0);
    }
}
