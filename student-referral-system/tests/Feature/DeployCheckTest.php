<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * php artisan deploy:check — stops a server going live in debug mode, open to
 * any browser origin, or with demo accounts still on the seeded password.
 */
class DeployCheckTest extends TestCase
{
    use RefreshDatabase;

    /** A configuration that should pass every hard check. */
    private function production(bool $mlUp = true): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
            'app.url' => 'https://portal.example.edu.ph',
            'cors.allowed_origins' => ['https://teacher.example.edu.ph', 'https://student.example.edu.ph'],
            'cors.allowed_origins_patterns' => [],
            'database.connections.mysql.username' => 'referral_app',
            'database.connections.mysql.password' => 'a-long-database-password',
            'services.ml.url' => 'http://127.0.0.1:8001',
            'services.ml.key' => 'shared-secret',
            'services.ml.retrain_enabled' => false,
        ]);
        Http::fake(['127.0.0.1:8001/*' => $mlUp
            ? Http::response(['status' => 'online'])
            : Http::response('', 500)]);
    }

    public function test_a_production_server_passes(): void
    {
        $this->production();
        User::factory()->teacher()->create(['password' => Hash::make('a-real-password-123')]);

        $this->artisan('deploy:check')
            ->expectsOutputToContain('Ready')
            ->assertSuccessful();
    }

    public function test_debug_mode_fails(): void
    {
        $this->production();
        config(['app.debug' => true]);

        $this->artisan('deploy:check')
            ->expectsOutputToContain('must be fixed')
            ->assertFailed();
    }

    public function test_a_demo_account_on_the_seeded_password_fails(): void
    {
        $this->production();
        User::factory()->teacher()->create(['email' => 'teacher@school.com', 'password' => Hash::make('password')]);

        $this->artisan('deploy:check')
            ->expectsOutputToContain('teacher@school.com')
            ->assertFailed();
    }

    public function test_localhost_browser_origins_fail(): void
    {
        $this->production();
        config(['cors.allowed_origins' => ['http://localhost:5101']]);

        $this->artisan('deploy:check')->assertFailed();
    }

    public function test_an_ml_engine_reachable_from_outside_without_a_key_fails(): void
    {
        $this->production();
        config(['services.ml.url' => 'http://10.0.0.5:8001', 'services.ml.key' => '']);
        Http::fake(['10.0.0.5:8001/*' => Http::response(['status' => 'online'])]);

        $this->artisan('deploy:check')->assertFailed();
    }

    public function test_an_unreachable_ml_engine_is_only_a_warning(): void
    {
        $this->production(mlUp: false);

        $this->artisan('deploy:check')
            ->expectsOutputToContain('no answer from http://127.0.0.1:8001')
            ->assertSuccessful();
    }
}
