<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_me_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'correct-password-123']);
        $user->settings()->create([]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password-123',
            'device_name' => 'Test iPhone',
        ])->assertOk()->json('data.token');

        $headers = ['Authorization' => 'Bearer '.$token];
        $this->withHeaders($headers)->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.settings.time_zone', 'Europe/Sofia');
        $this->withHeaders($headers)->postJson('/api/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withHeaders($headers)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_invalid_credentials_do_not_issue_token(): void
    {
        $user = User::factory()->create();
        $this->withHeader('Accept-Language', 'bg')->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
            'device_name' => 'Test iPhone',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.message', 'Невалиден имейл или парола.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_api_request_without_json_accept_header_uses_error_envelope(): void
    {
        $this->get('/api/v1/me')->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_user_sees_only_own_identity_and_devices(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $alice->settings()->create([]);
        $bob->settings()->create([]);
        $bob->devices()->create(['name' => 'Bob phone', 'installation_id' => 'bob-install']);

        $token = $alice->createToken('Alice phone')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $response->assertJsonPath('data.id', $alice->id)
            ->assertDontSee($bob->id)->assertDontSee($bob->email)
            ->assertDontSee('bob-install');
        $bobDevice = $bob->devices()->firstOrFail();
        $this->assertFalse(Gate::forUser($alice)->allows('view', $bobDevice));
        $this->withToken($token)->getJson('/api/v1/devices/'.$bobDevice->id)
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->assertCount(0, $alice->devices);
    }

    public function test_only_admin_can_access_filament(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $normal = User::factory()->create(['is_admin' => false]);
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($normal->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($normal)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_first_admin_command_prompts_for_password_and_creates_settings(): void
    {
        $this->artisan('hustle:create-user', ['email' => 'owner@example.test', '--admin' => true])
            ->expectsQuestion('Name', 'Owner')
            ->expectsQuestion('Password (at least 12 characters)', 'a-local-test-password')
            ->expectsQuestion('Confirm password', 'a-local-test-password')
            ->assertExitCode(0);

        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertEquals('Europe/Sofia', $user->settings->time_zone);
    }
}
