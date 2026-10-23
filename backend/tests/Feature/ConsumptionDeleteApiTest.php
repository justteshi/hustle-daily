<?php

namespace Tests\Feature;

use App\Models\Consumption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionDeleteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_removes_meal_from_totals_and_repeated_request_succeeds(): void
    {
        $user = User::factory()->create();
        $category = $user->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $food = $user->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $this->actingAs($user);
        $id = $this->withHeader('Idempotency-Key', 'create-breakfast')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-22T08:00:00+03:00',
            'items' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');

        $this->withHeader('Idempotency-Key', 'delete-breakfast')->deleteJson('/api/v1/consumptions/'.$id)->assertNoContent();
        $this->withHeader('Idempotency-Key', 'delete-breakfast')->deleteJson('/api/v1/consumptions/'.$id)->assertNoContent();

        $this->assertSoftDeleted('consumptions', ['id' => $id]);
        $this->assertDatabaseCount('consumption_items', 1);
        $this->getJson('/api/v1/consumptions?date=2026-10-22')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/consumptions/dates')->assertOk()->assertJsonCount(0, 'data');
        $this->withHeader('Idempotency-Key', 'another-delete')->deleteJson('/api/v1/consumptions/'.$id)->assertNotFound();
        $this->withHeader('Idempotency-Key', 'edit-after-delete')->patchJson('/api/v1/consumptions/'.$id, ['meal_category_id' => $category->id])->assertNotFound();
    }

    public function test_other_user_cannot_delete_meal(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $meal = $owner->consumptions()->create([
            'meal_category_id' => $category->id, 'eaten_at' => now(), 'local_date' => '2026-10-22',
            'idempotency_key' => 'create', 'request_fingerprint' => str_repeat('a', 64),
            'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0,
        ]);

        $this->actingAs($other)->withHeader('Idempotency-Key', 'foreign-delete')
            ->deleteJson('/api/v1/consumptions/'.$meal->id)->assertNotFound();

        $this->assertModelExists($meal);
        $this->assertSame(null, Consumption::withTrashed()->findOrFail($meal->id)->deleted_at);
    }
}
