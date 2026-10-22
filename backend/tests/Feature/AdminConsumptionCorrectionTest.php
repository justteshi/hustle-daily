<?php

namespace Tests\Feature;

use App\Filament\Resources\Consumptions\Pages\EditConsumption;
use App\Models\Consumption;
use App\Models\User;
use App\Services\CorrectConsumption;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminConsumptionCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_corrects_past_ingredients_and_keeps_a_revision(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $owner->settings()->create([]);
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $skyr = $owner->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $skyr->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $milk = $owner->foods()->create(['name' => 'Milk', 'brand' => 'B']);
        $milk->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'ml', 'calories' => 50, 'protein' => 3, 'carbohydrates' => 5, 'fat' => 2]);
        $this->actingAs($owner);
        $id = $this->withHeader('Idempotency-Key', 'past-breakfast')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-20T08:00:00+03:00',
            'items' => [['food_id' => $skyr->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditConsumption::class, ['record' => $id])
            ->fillForm(['reason' => 'Forgot the milk', 'correction_items' => [
                ['food_id' => $skyr->id, 'quantity' => 100, 'unit' => 'g'],
                ['food_id' => $milk->id, 'quantity' => 80, 'unit' => 'ml'],
            ]])->call('save')->assertHasNoFormErrors();

        $log = Consumption::with(['items', 'revisions'])->findOrFail($id);
        $this->assertSame('2026-10-20', $log->local_date->toDateString());
        $this->assertSame('100.000', $log->calories);
        $this->assertCount(2, $log->items);
        $this->assertSame('ml', $log->items->last()->input_unit);
        $this->assertCount(1, $log->revisions);
        $this->assertSame('60.000', $log->revisions->first()->before_state['calories']);
        $this->assertSame('100.000', $log->revisions->first()->after_state['calories']);
        $this->get('/admin/consumption-revisions')->assertOk();
        $this->get('/admin/consumption-revisions/create')->assertNotFound();
    }

    public function test_metadata_correction_preserves_immutable_nutrition(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $food = $owner->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $this->actingAs($owner);
        $id = $this->withHeader('Idempotency-Key', 'metadata-test')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-20T08:00:00+03:00',
            'items' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');
        $food->nutrition()->update(['calories' => 99]);

        app(CorrectConsumption::class)->handle(Consumption::findOrFail($id), $owner, [
            'meal_category_id' => $category->id, 'notes' => 'Late entry', 'reason' => 'Correcting the note',
        ]);

        $log = Consumption::with(['items', 'revisions'])->findOrFail($id);
        $this->assertSame('60.000', $log->calories);
        $this->assertSame('60.000', $log->items->first()->calories);
        $this->assertSame('Late entry', $log->notes);
        $this->assertCount(1, $log->revisions);
    }

    public function test_non_admin_cannot_open_correction_page(): void
    {
        $owner = User::factory()->create();
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $food = $owner->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $this->actingAs($owner);
        $id = $this->withHeader('Idempotency-Key', 'owner-log')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-20T08:00:00+03:00',
            'items' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');

        $this->get('/admin/consumptions/'.$id.'/edit')->assertForbidden();
    }

    public function test_cross_user_food_correction_rolls_back_and_creates_no_revision(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $food = $owner->foods()->create(['name' => 'Skyr', 'brand' => 'A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $foreignFood = $admin->foods()->create(['name' => 'Private', 'brand' => 'B']);
        $foreignFood->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 99, 'protein' => 3, 'carbohydrates' => 4, 'fat' => 2]);
        $this->actingAs($owner);
        $id = $this->withHeader('Idempotency-Key', 'cross-user-correction')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-20T08:00:00+03:00',
            'items' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');

        try {
            app(CorrectConsumption::class)->handle(Consumption::findOrFail($id), $admin, [
                'meal_category_id' => $category->id, 'reason' => 'Wrong food was selected',
                'correction_items' => [['food_id' => $foreignFood->id, 'quantity' => 100, 'unit' => 'g']],
            ]);
            $this->fail('Cross-user food was accepted.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('consumption_revisions', 0);
            $this->assertDatabaseHas('consumptions', ['id' => $id, 'calories' => 60]);
            $this->assertDatabaseHas('consumption_items', ['consumption_id' => $id, 'food_id' => $food->id]);
        }

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(EditConsumption::class, ['record' => $id])
            ->fillForm(['reason' => 'Wrong food was selected', 'correction_items' => [
                ['food_id' => $foreignFood->id, 'quantity' => 100, 'unit' => 'g'],
            ]])->call('save')->assertHasFormErrors(['correction_items']);
        $this->assertDatabaseCount('consumption_revisions', 0);
    }
}
