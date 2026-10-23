<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class WebSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_session_authenticates_api_without_mobile_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct password')]);
        $this->getJson('/session/csrf')->assertOk()->assertJsonStructure(['data' => ['csrf_token']]);
        $this->postJson('/session/login', ['email' => $user->email, 'password' => 'correct password'])->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/session/logout')->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_browser_session_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create();
        $this->postJson('/session/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_password_recovery_signs_out_existing_browser_session(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);
        $this->postJson('/session/login', ['email' => $user->email, 'password' => 'old-password-123'])->assertOk();
        Auth::forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->getJson('/api/v1/me')->assertOk();
        $this->artisan('hustle:reset-user-password', ['email' => $user->email])
            ->expectsQuestion('New password (at least 12 characters)', 'new-password-456')
            ->expectsQuestion('Confirm new password', 'new-password-456')
            ->assertExitCode(0);

        Auth::forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->getJson('/api/v1/me')->assertUnauthorized();
    }
}
