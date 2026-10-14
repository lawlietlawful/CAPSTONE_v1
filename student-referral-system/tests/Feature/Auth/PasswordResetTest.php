<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * There is no email reset: mail isn't set up (the link never arrived) and
 * the form told strangers which emails had accounts. "Forgot password?"
 * explains who resets passwords in person instead.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_page_explains_who_resets_passwords(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'), false);

        $this->get('/forgot-password')->assertOk()
            ->assertSee('data-reset-staff', false)
            ->assertSee('Super Admin')
            ->assertSee('data-reset-teacher-student', false)
            ->assertSee('activation code')
            ->assertDontSee('<form', false);
    }

    public function test_reset_links_can_no_longer_be_requested_or_used(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertStatus(405);
        $this->get('/reset-password/some-token')->assertNotFound();
        $this->post('/reset-password', [
            'token' => 'some-token', 'email' => $user->email,
            'password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026',
        ])->assertNotFound();

        Notification::assertNothingSent();
    }
}
