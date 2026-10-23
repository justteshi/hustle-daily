<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ApiDocsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_view_the_generated_api_document(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewApiDocs'));
        $this->assertFalse(Gate::forUser($regularUser)->allows('viewApiDocs'));

        $this->get('/docs/api.json')->assertForbidden();
        $this->actingAs($regularUser)->get('/docs/api.json')->assertForbidden();
        $this->actingAs($regularUser)->get('/docs/api')->assertForbidden();
        $this->actingAs($admin)->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['openapi', 'paths' => ['/v1/me']]);
        $this->actingAs($admin)->get('/docs/api')->assertOk()->assertSee('elements-api', false);
    }
}
