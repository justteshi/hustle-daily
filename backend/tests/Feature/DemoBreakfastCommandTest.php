<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoBreakfastCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_breakfast_is_scoped_and_rerunning_does_not_duplicate_or_replace_foods(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->artisan('hustle:seed-demo-breakfast', ['email' => $owner->email])->assertExitCode(0);
        $skyr = $owner->foods()->where('idempotency_key', 'demo-breakfast-skyr-v1')->firstOrFail();
        $skyr->nutrition()->update(['calories' => 62]);
        $this->artisan('hustle:seed-demo-breakfast', ['email' => $owner->email])->assertExitCode(0);

        $this->assertSame(3, $owner->foods()->count());
        $this->assertSame(1, $owner->recipes()->count());
        $this->assertSame(3, $owner->recipes()->firstOrFail()->ingredients()->count());
        $this->assertSame('62.000', $skyr->nutrition()->firstOrFail()->calories);
        $this->assertSame(0, $other->foods()->count());
        $this->assertSame(0, $other->recipes()->count());
    }

    public function test_demo_breakfast_requires_an_existing_user(): void
    {
        $this->artisan('hustle:seed-demo-breakfast', ['email' => 'missing@example.test'])->assertExitCode(1);

        $this->assertDatabaseCount('foods', 0);
        $this->assertDatabaseCount('recipes', 0);
    }
}
