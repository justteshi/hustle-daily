<?php

namespace Tests\Feature;

use App\Filament\Resources\Food\Pages\CreateFood;
use App\Filament\Resources\Food\Pages\EditFood;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_edit_pages_and_non_admin_cannot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $normal = User::factory()->create();
        $food = $admin->foods()->create(['name' => 'Skyr', 'brand' => 'Demo']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $recipe = $admin->recipes()->create(['name' => 'Breakfast', 'yield_servings' => 1]);
        $recipe->ingredients()->create(['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g', 'sort_order' => 0]);

        foreach (['/admin/food/'.$food->id.'/edit', '/admin/recipes/'.$recipe->id.'/edit', '/admin/users/'.$admin->id.'/edit'] as $path) {
            $this->actingAs($normal)->get($path)->assertForbidden();
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_admin_food_edit_preserves_existing_consumption_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $food = $admin->foods()->create(['name' => 'Skyr', 'brand' => 'Demo']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $category = $admin->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $this->actingAs($admin);
        $consumptionId = $this->withHeader('Idempotency-Key', 'admin-edit-snapshot')->postJson('/api/v1/consumptions', [
            'meal_category_id' => $category->id, 'eaten_at' => '2026-10-22T08:00:00+03:00',
            'items' => [['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g']],
        ])->assertCreated()->json('data.id');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditFood::class, ['record' => $food->id])
            ->fillForm(['name' => 'Updated Skyr', 'nutrition' => ['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 62, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('Updated Skyr', $food->fresh()->name);
        $this->assertSame('62.000', $food->nutrition()->firstOrFail()->calories);
        $this->assertDatabaseHas('consumption_items', ['consumption_id' => $consumptionId, 'food_name' => 'Skyr', 'calories' => 60]);
    }

    public function test_admin_can_edit_recipe_and_user_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->settings()->create([]);
        $food = $admin->foods()->create(['name' => 'Skyr', 'brand' => 'Demo']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $recipe = $admin->recipes()->create(['name' => 'Breakfast', 'yield_servings' => 1]);
        $recipe->ingredients()->create(['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g', 'sort_order' => 0]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditRecipe::class, ['record' => $recipe->id])
            ->fillForm(['name' => 'Updated Breakfast', 'yield_servings' => 2, 'ingredients' => [
                ['food_id' => $food->id, 'quantity' => 80, 'unit' => 'g'],
            ]])
            ->call('save')->assertHasNoFormErrors();
        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->fillForm(['name' => 'Updated Admin', 'settings' => ['locale' => 'bg', 'time_zone' => 'Europe/Sofia']])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('Updated Breakfast', $recipe->fresh()->name);
        $this->assertSame('80.000', $recipe->ingredients()->firstOrFail()->quantity);
        $this->assertSame('Updated Admin', $admin->fresh()->name);
        $this->assertSame('bg', $admin->settings()->firstOrFail()->locale);
    }

    public function test_admin_can_create_food_with_portion_and_a_ready_meal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateFood::class)
            ->fillForm([
                'user_id' => $admin->id, 'name' => 'Whey', 'brand' => 'Demo',
                'nutrition' => ['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 390, 'protein' => 78, 'carbohydrates' => 7, 'fat' => 6],
                'portions' => [['label' => 'scoop', 'base_amount' => 30, 'base_unit' => 'g']],
            ])->call('create')->assertHasNoFormErrors();
        $food = $admin->foods()->firstOrFail();
        $portion = $food->portions()->firstOrFail();

        Livewire::test(CreateRecipe::class)
            ->fillForm([
                'user_id' => $admin->id, 'name' => 'Demo breakfast', 'yield_servings' => 1,
                'ingredients' => [['food_id' => $food->id, 'quantity' => 1, 'unit' => 'portion', 'food_portion_id' => $portion->id]],
            ])->call('create')->assertHasNoFormErrors();

        $this->assertSame('30.000', $portion->base_amount);
        $this->assertSame(1, $admin->recipes()->count());
        $this->assertSame($portion->id, $admin->recipes()->firstOrFail()->ingredients()->firstOrFail()->food_portion_id);
    }

    public function test_recipe_ingredient_cannot_use_another_users_food(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $food = $other->foods()->create(['name' => 'Private', 'brand' => 'Demo']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $recipe = $owner->recipes()->create(['name' => 'Wrong owner', 'yield_servings' => 1]);

        $this->expectException(ValidationException::class);
        $recipe->ingredients()->create(['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g', 'sort_order' => 0]);
    }

    public function test_admin_cannot_demote_the_last_administrator(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->settings()->create([]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->fillForm(['is_admin' => false])
            ->call('save')->assertHasFormErrors(['is_admin']);

        $this->assertTrue($admin->fresh()->is_admin);
    }
}
