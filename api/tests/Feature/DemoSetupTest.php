<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prints_a_working_token_and_rotates_it_on_rerun(): void
    {
        Artisan::call('demo:setup');
        $first = trim(Artisan::output());
        Artisan::call('demo:setup');
        $second = trim(Artisan::output());

        $this->assertSame(1, User::where('email', 'underwriter@example.test')->count());
        $this->assertNotSame($first, $second);
        $this->withToken($second)->getJson('/api/applications')->assertOk();
        $this->assertSame(1, User::first()->tokens()->count());
    }
}
