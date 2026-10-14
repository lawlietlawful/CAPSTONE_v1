<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The sign-in page shared by every role: escalating pauses per account,
 * a limit per device across accounts, equal work for unknown accounts,
 * no "remember me", and a "last sign-in" notice for staff.
 */
class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role' => 'admin', 'email' => 'cruz@school.test', 'password' => bcrypt('RightPass2026'),
        ]);
    }

    private function attempt(string $email, string $password, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login'), ['email' => $email, 'password' => $password]);
    }

    private function failTimes(int $times, string $email = 'cruz@school.test', string $ip = '10.0.0.1')
    {
        for ($i = 0; $i < $times; $i++) {
            $response = $this->attempt($email, 'wrong-guess', $ip);
        }

        return $response;
    }

    // ── The page ─────────────────────────────────────────────────────────

    public function test_the_login_page_is_branded_and_has_its_helpers(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Misamis University')
            ->assertSee('Student Referral System')
            ->assertSee('data-toggle-password', false)
            ->assertSee('data-caps-warning', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('name="remember"', false)
            ->assertDontSee('Laravel');
    }

    public function test_empty_fields_get_plain_messages(): void
    {
        $this->post(route('login'), [])->assertSessionHasErrors([
            'email' => 'Enter your email or ID.',
            'password' => 'Enter your password.',
        ]);
    }

    // ── No "remember me" ─────────────────────────────────────────────────

    public function test_sign_ins_are_never_remembered(): void
    {
        $user = $this->counselor(['remember_token' => null]);

        $response = $this->post(route('login'), ['email' => 'cruz@school.test', 'password' => 'RightPass2026', 'remember' => 'on']);

        $this->assertAuthenticatedAs($user);
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_the_migration_forgets_sign_ins_already_remembered(): void
    {
        $remembered = $this->counselor(['remember_token' => 'old-token-from-a-shared-pc']);

        (require database_path('migrations/2026_10_14_000000_forget_remembered_sign_ins.php'))->up();

        $this->assertNull($remembered->fresh()->remember_token);
    }

    // ── Pauses per account ───────────────────────────────────────────────

    public function test_five_wrong_passwords_pause_the_account_even_for_the_right_one(): void
    {
        $this->counselor();

        $this->failTimes(4)->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->failTimes(1)->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please try again in 1 minute.']);

        $this->attempt('cruz@school.test', 'RightPass2026')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->attempt('cruz@school.test', 'RightPass2026');
        $this->assertAuthenticated();
    }

    public function test_the_pause_gets_longer_when_the_guessing_carries_on(): void
    {
        $this->counselor();

        $this->failTimes(5)->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please try again in 1 minute.']);
        $this->travel(61)->seconds();
        $this->failTimes(5)->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please try again in 5 minutes.']);
        $this->travel(301)->seconds();
        $this->failTimes(5)->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please try again in 15 minutes.']);

        // Still paused 14 minutes in.
        $this->travel(14)->minutes();
        $this->attempt('cruz@school.test', 'RightPass2026');
        $this->assertGuest();
    }

    public function test_the_page_counts_the_pause_down(): void
    {
        $this->counselor();
        $this->failTimes(5);

        $this->get(route('login'))
            ->assertSee('loginForm(60)', false)
            ->assertSee('01:00');
    }

    public function test_a_pause_is_logged_once_not_for_every_attempt_during_it(): void
    {
        $this->counselor();

        $this->failTimes(5);
        $this->failTimes(6);   // all refused while paused

        $this->assertSame(1, AuditLog::where('action', 'auth.lockout')->count());
        $this->assertSame(5, AuditLog::where('action', 'auth.failed')->count());
    }

    public function test_the_pause_is_per_device_so_the_owner_elsewhere_is_not_locked_out(): void
    {
        $user = $this->counselor();
        $this->failTimes(5, ip: '203.0.113.9');

        $this->attempt('cruz@school.test', 'RightPass2026', ip: '10.0.0.1');

        $this->assertAuthenticatedAs($user);
    }

    // ── Limit per device, across accounts ────────────────────────────────

    public function test_one_device_cannot_try_password_after_password_across_accounts(): void
    {
        $this->counselor();

        for ($i = 1; $i <= LoginRequest::DEVICE_MAX_FAILURES; $i++) {
            $this->attempt("user{$i}@school.test", 'Password123');
        }

        // Even the right password for a real account is refused from here...
        $this->attempt('cruz@school.test', 'RightPass2026')
            ->assertSessionHasErrors(['email' => 'Too many failed sign-ins from this device. Please try again in 15 minutes.']);
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'auth.lockout')->where('description', 'like', '%across accounts%')->count());

        // ...but not from another device.
        $this->attempt('cruz@school.test', 'RightPass2026', ip: '10.0.0.2');
        $this->assertAuthenticated();
    }

    public function test_signing_into_your_own_account_does_not_reset_the_device_limit(): void
    {
        $own = User::factory()->create(['role' => 'admin', 'email' => 'mine@school.test', 'password' => bcrypt('MyOwnPass2026')]);

        for ($i = 1; $i < LoginRequest::DEVICE_MAX_FAILURES; $i++) {
            $this->attempt("user{$i}@school.test", 'Password123');
        }
        $this->attempt('mine@school.test', 'MyOwnPass2026');
        $this->assertAuthenticatedAs($own);
        $this->post(route('logout'));

        $this->attempt('another@school.test', 'Password123');
        $this->attempt('mine@school.test', 'MyOwnPass2026')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ── Unknown accounts take the same work ──────────────────────────────

    public function test_an_unknown_account_still_checks_a_password_hash(): void
    {
        Cache::forever('login.dummy-hash.' . config('hashing.bcrypt.rounds'), Hash::make('anything'));
        Hash::partialMock()->shouldReceive('check')->once()->andReturnFalse();

        $this->attempt('nobody@school.test', 'Password123')->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    public function test_failed_attempts_on_a_real_account_name_it_as_the_subject(): void
    {
        $user = $this->counselor();

        $this->attempt('cruz@school.test', 'wrong-guess');
        $this->attempt('nobody@school.test', 'wrong-guess');

        $this->assertSame($user->id, AuditLog::where('description', 'like', '%cruz@school.test')->sole()->subject_id);
        $this->assertNull(AuditLog::where('description', 'like', '%nobody@school.test')->sole()->subject_id);
    }

    // ── Last sign-in notice (staff) ──────────────────────────────────────

    public function test_a_first_sign_in_says_so(): void
    {
        $this->counselor();

        $this->followingRedirects()->post(route('login'), ['email' => 'cruz@school.test', 'password' => 'RightPass2026'])
            ->assertOk()
            ->assertSee('data-last-sign-in', false)
            ->assertSee('This is the first recorded sign-in to your account.')
            ->assertDontSee('data-failed-attempts', false);
    }

    public function test_staff_see_their_previous_sign_in_and_failed_attempts_since(): void
    {
        $user = $this->counselor();
        $other = User::factory()->create(['role' => 'admin']);

        $this->travelTo(now()->subDays(3));
        AuditLog::record('auth.failed', $user, 'Failed sign-in attempt for cruz@school.test', null);   // before the last sign-in
        $this->travelBack();
        $this->travelTo(now()->subDays(2)->setTime(15, 14));
        AuditLog::record('auth.login', null, 'Signed in', $user)->update(['ip_address' => '192.168.1.20']);
        $this->travelBack();
        $this->travelTo(now()->subDay());
        AuditLog::record('auth.failed', $user, 'Failed sign-in attempt for cruz@school.test', null);
        AuditLog::record('auth.failed', $user, 'Failed sign-in attempt for cruz@school.test', null);
        AuditLog::record('auth.failed', $other, 'Failed sign-in attempt for someone else', null);
        $this->travelBack();

        $this->followingRedirects()->post(route('login'), ['email' => 'cruz@school.test', 'password' => 'RightPass2026'])
            ->assertOk()
            ->assertSee('Last sign-in:')
            ->assertSee(now()->subDays(2)->format('M j, Y') . ' at 3:14 PM')
            ->assertSee('from 192.168.1.20')
            ->assertSee('data-failed-attempts="2"', false)
            ->assertSee('2 failed sign-in attempts')
            ->assertSee(route('account.password.edit'), false);
    }

    public function test_the_notice_shows_once(): void
    {
        $user = $this->counselor();
        $this->followingRedirects()->post(route('login'), ['email' => 'cruz@school.test', 'password' => 'RightPass2026'])
            ->assertSee('data-last-sign-in', false);

        $this->actingAs($user)->get(route('counselor.dashboard'))->assertOk()->assertDontSee('data-last-sign-in', false);
    }

    public function test_the_super_admin_sees_it_too_but_teachers_do_not(): void
    {
        User::factory()->create(['role' => 'super_admin', 'email' => 'head@school.test', 'password' => bcrypt('RightPass2026')]);
        $this->followingRedirects()->post(route('login'), ['email' => 'head@school.test', 'password' => 'RightPass2026'])
            ->assertOk()->assertSee('data-last-sign-in', false);
        $this->post(route('logout'));

        $teacher = User::factory()->teacher()->create(['email' => 'teach@school.test', 'password' => bcrypt('RightPass2026')]);
        $this->post(route('login'), ['email' => 'teach@school.test', 'password' => 'RightPass2026']);
        $this->assertAuthenticatedAs($teacher);
        $this->assertNull(session('last_sign_in'));
    }
}
