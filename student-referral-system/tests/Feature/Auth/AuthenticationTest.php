<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        // This app redirects to a role-specific dashboard on login
        // (AuthenticatedSessionController), not the generic Breeze /dashboard.
        $user = User::factory()->teacher()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/teacher/dashboard');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    /**
     * Students/teachers are frequently created with no email at all and are
     * given a Student/Employee ID (the `username` column) instead — the web
     * login form must accept that identifier, not just a real email address.
     */
    public function test_a_student_can_authenticate_using_their_student_id_instead_of_email(): void
    {
        $user = User::factory()->student()->create([
            'email' => null,
            'username' => '2026-0099',
        ]);

        $response = $this->post('/login', [
            'email' => '2026-0099',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/student/dashboard');
    }

    public function test_a_teacher_can_authenticate_using_their_employee_id_instead_of_email(): void
    {
        $user = User::factory()->teacher()->create([
            'email' => null,
            'username' => 'T-2026-999',
        ]);

        $response = $this->post('/login', [
            'email' => 'T-2026-999',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/teacher/dashboard');
    }

    public function test_users_can_not_authenticate_with_an_id_that_does_not_exist(): void
    {
        $this->post('/login', [
            'email' => 'no-such-id',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
