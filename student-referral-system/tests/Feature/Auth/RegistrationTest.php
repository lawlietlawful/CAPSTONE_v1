<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There is no public sign-up: staff accounts come from the super admin and
 * teacher/student accounts from activation codes. Breeze's /register used
 * to create a 'student' account for anyone who could reach the site.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_registration_screen(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_nobody_can_sign_themselves_up(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
