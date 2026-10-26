<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DayTargets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayTargetsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/day-types')->assertUnauthorized();
    }

    public function test_returns_account_defaults_without_duplicate_creation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/day-types')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.key', 'training')
            ->assertJsonPath('data.0.targets.steps.value', 10000)
            ->assertJsonPath('data.0.targets.consumed_calories.value', 2000)
            ->assertJsonPath('data.1.key', 'rest')
            ->assertJsonPath('data.1.targets.protein_g.value', 120);
        $this->actingAs($user)->getJson('/api/v1/day-types')->assertOk();

        $this->assertDatabaseCount('day_types', 2);
        $this->assertDatabaseCount('target_profiles', 2);
        $this->assertDatabaseCount('target_values', 5);
    }

    public function test_manual_assignment_changes_today_targets(): void
    {
        $this->travelTo('2026-10-26 10:00:00');
        $user = User::factory()->create();
        $training = $this->actingAs($user)->getJson('/api/v1/day-types')->json('data.0');

        $this->actingAs($user)->patchJson('/api/v1/days/2026-10-26', [
            'day_type_id' => $training['id'],
        ])->assertOk()->assertJsonPath('data.source', 'manual');
        $this->actingAs($user)->getJson('/api/v1/dashboard/today')->assertOk()
            ->assertJsonPath('data.day_type.key', 'training')
            ->assertJsonPath('data.day_type.targets.protein_g.value', 160)
            ->assertJsonPath('data.health', null);

        $this->assertDatabaseHas('day_assignments', [
            'user_id' => $user->id, 'local_date' => '2026-10-26', 'source' => 'manual',
        ]);
    }

    public function test_returns_404_for_another_users_day_type(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $type = $this->actingAs($owner)->getJson('/api/v1/day-types')->json('data.0');

        $this->actingAs($intruder)->patchJson('/api/v1/days/2026-10-26', [
            'day_type_id' => $type['id'],
        ])->assertNotFound();
        $this->actingAs($intruder)->putJson('/api/v1/day-types/'.$type['id'], [
            'targets' => ['consumed_calories' => 2100, 'protein_g' => 140],
        ])->assertNotFound();

        $this->assertDatabaseCount('day_assignments', 0);
    }

    public function test_target_edit_updates_values_and_rejects_invalid_input(): void
    {
        $this->travelTo('2026-10-26 10:00:00');
        $user = User::factory()->create();
        $type = $this->actingAs($user)->getJson('/api/v1/day-types')->json('data.0');

        $this->actingAs($user)->putJson('/api/v1/day-types/'.$type['id'], [
            'targets' => ['consumed_calories' => 2300, 'protein_g' => 175, 'steps' => null],
        ])->assertOk()->assertJsonPath('data.targets.consumed_calories.value', 2300)
            ->assertJsonPath('data.targets.protein_g.value', 175)
            ->assertJsonMissingPath('data.targets.steps');
        $this->actingAs($user)->putJson('/api/v1/day-types/'.$type['id'], [
            'targets' => ['consumed_calories' => -1, 'protein_g' => 175],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['targets.consumed_calories']]]);

        $this->assertDatabaseCount('target_profiles', 3);
        $this->assertDatabaseCount('target_values', 7);
    }

    public function test_target_edits_preserve_past_targets_and_replace_same_day_version(): void
    {
        $this->travelTo('2026-10-26 10:00:00');
        $user = User::factory()->create();
        $type = $this->actingAs($user)->getJson('/api/v1/day-types')->json('data.0');

        $this->actingAs($user)->putJson('/api/v1/day-types/'.$type['id'], [
            'targets' => ['consumed_calories' => 2300, 'protein_g' => 175],
        ])->assertOk();
        $this->actingAs($user)->putJson('/api/v1/day-types/'.$type['id'], [
            'targets' => ['consumed_calories' => 2400, 'protein_g' => 180],
        ])->assertOk()->assertJsonPath('data.targets.consumed_calories.value', 2400);

        $dayType = $user->dayTypes()->findOrFail($type['id']);
        $targets = app(DayTargets::class);
        $this->assertSame(2000.0, $targets->serialize($dayType, '2026-10-25')['targets']['consumed_calories']['value']);
        $this->assertSame(2400.0, $targets->serialize($dayType, '2026-10-26')['targets']['consumed_calories']['value']);
        $this->assertDatabaseCount('target_profiles', 3);
        $this->assertDatabaseHas('target_profiles', [
            'day_type_id' => $type['id'], 'effective_to' => '2026-10-25',
        ]);
    }

    public function test_returns_404_for_invalid_local_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/days/2026-02-30', [
            'day_type_id' => '00000000-0000-4000-8000-000000000000',
        ])->assertNotFound();

        $this->assertDatabaseCount('day_assignments', 0);
    }
}
