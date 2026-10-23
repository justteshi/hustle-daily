<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_contains_only_owners_records_and_no_password_or_tokens(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->settings()->create([]);
        $owner->foods()->create(['name' => 'Private food', 'brand' => 'A']);
        $other->foods()->create(['name' => 'Other food', 'brand' => 'B']);
        $token = $owner->createToken('iPhone')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/account/export')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.account.id', $owner->id)
            ->assertJsonPath('data.foods.0.name', 'Private food')
            ->assertDontSee('Other food')->assertDontSee($other->email)
            ->assertDontSee($owner->password)->assertDontSee($token);
    }

    public function test_password_confirmed_deletion_removes_only_owners_data(): void
    {
        $owner = User::factory()->create(['password' => 'correct-password-123']);
        $other = User::factory()->create();
        $food = $owner->foods()->create(['name' => 'Private food', 'brand' => 'A']);
        $food->nutrition()->create(['basis_amount' => 100, 'basis_unit' => 'g', 'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0]);
        $other->foods()->create(['name' => 'Other food', 'brand' => 'B']);
        $recipe = $owner->recipes()->create(['name' => 'Breakfast', 'yield_servings' => 1]);
        $recipe->ingredients()->create(['food_id' => $food->id, 'quantity' => 100, 'unit' => 'g', 'sort_order' => 1]);
        $category = $owner->mealCategories()->create(['key' => 'breakfast', 'name' => 'Breakfast', 'sort_order' => 1]);
        $meal = $owner->consumptions()->create([
            'meal_category_id' => $category->id, 'eaten_at' => now(), 'local_date' => '2026-10-22',
            'idempotency_key' => 'breakfast', 'request_fingerprint' => str_repeat('a', 64),
            'calories' => 60, 'protein' => 11, 'carbohydrates' => 4, 'fat' => 0,
        ]);
        $meal->revisions()->create([
            'user_id' => $owner->id, 'actor_user_id' => $owner->id, 'revision_number' => 1,
            'reason' => 'Correct breakfast', 'before_state' => [], 'after_state' => [], 'created_at' => now(),
        ]);
        $token = $owner->createToken('iPhone')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/account', ['password' => 'wrong'])->assertUnprocessable();
        $this->assertModelExists($owner);
        $this->withToken($token)->deleteJson('/api/v1/account', ['password' => 'correct-password-123'])->assertNoContent();

        $this->assertModelMissing($owner);
        $this->assertDatabaseMissing('foods', ['user_id' => $owner->id]);
        $this->assertDatabaseMissing('recipes', ['user_id' => $owner->id]);
        $this->assertDatabaseMissing('consumption_revisions', ['user_id' => $owner->id]);
        $this->assertModelExists($other);
        $this->assertDatabaseHas('foods', ['user_id' => $other->id]);
    }

    public function test_account_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/account/export')->assertUnauthorized();
        $this->deleteJson('/api/v1/account', ['password' => 'anything'])->assertUnauthorized();
    }

    public function test_console_recovery_changes_password_and_revokes_mobile_token(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);
        $user->createToken('iPhone');

        $this->artisan('hustle:reset-user-password', ['email' => $user->email])
            ->expectsQuestion('New password (at least 12 characters)', 'new-password-456')
            ->expectsQuestion('Confirm new password', 'new-password-456')
            ->assertExitCode(0);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'old-password-123', 'device_name' => 'Phone'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'new-password-456', 'device_name' => 'Phone'])->assertOk();
    }

    public function test_deleting_admin_preserves_other_users_meal_audit_without_admin_identity(): void
    {
        $admin = User::factory()->create(['password' => 'correct-password-123', 'is_admin' => true]);
        $other = User::factory()->create();
        $category = $other->mealCategories()->create(['key' => 'lunch', 'name' => 'Lunch', 'sort_order' => 1]);
        $meal = $other->consumptions()->create([
            'meal_category_id' => $category->id, 'eaten_at' => now(), 'local_date' => '2026-10-22',
            'idempotency_key' => 'lunch', 'request_fingerprint' => str_repeat('a', 64),
            'calories' => 100, 'protein' => 10, 'carbohydrates' => 10, 'fat' => 2,
        ]);
        $revision = $meal->revisions()->create([
            'user_id' => $other->id, 'actor_user_id' => $admin->id,
            'revision_number' => 1, 'reason' => 'Admin correction',
            'before_state' => [], 'after_state' => [], 'created_at' => now(),
        ]);

        $this->actingAs($admin)->deleteJson('/api/v1/account', ['password' => 'correct-password-123'])->assertNoContent();

        $this->assertModelMissing($admin);
        $this->assertModelExists($revision);
        $this->assertDatabaseHas('consumption_revisions', ['id' => $revision->id, 'actor_user_id' => null]);
    }
}
