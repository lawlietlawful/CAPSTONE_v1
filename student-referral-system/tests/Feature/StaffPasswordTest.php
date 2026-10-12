<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Staff passwords:
 *
 *  - Resetting a counselor/admin used to set "password123" for everyone,
 *    kept indefinitely, with their sessions left signed in. Now: a random
 *    temporary password shown once, a forced change at next sign-in, and
 *    every session of theirs signed out.
 *  - Staff had no way to change their own password.
 */
class StaffPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function counselor(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'admin']);
    }

    // ── Reset ───────────────────────────────────────────────────────────

    public function test_a_reset_gives_a_one_time_random_password_and_requires_a_change(): void
    {
        $counselor = $this->counselor(['name' => 'Sir Cruz', 'email' => 'cruz@school.test']);
        $counselor->createToken('device');

        $response = $this->actingAs($this->superAdmin())->post(route('admin.users.reset-password', $counselor->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('temporary_password');

        $temp = session('temporary_password');
        $this->assertSame(['Sir Cruz', 'cruz@school.test'], [$temp['name'], $temp['email']]);
        $this->assertSame(12, strlen($temp['password']));
        $this->assertNotSame('password123', $temp['password']);

        $fresh = $counselor->fresh();
        $this->assertTrue(Hash::check($temp['password'], $fresh->password));
        $this->assertFalse(Hash::check('password123', $fresh->password));
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame(0, $fresh->tokens()->count());
    }

    public function test_two_resets_never_give_the_same_password(): void
    {
        $admin = $this->superAdmin();
        $a = $this->counselor();
        $b = $this->counselor();

        $this->actingAs($admin)->post(route('admin.users.reset-password', $a->id));
        $first = session('temporary_password')['password'];
        $this->actingAs($admin)->post(route('admin.users.reset-password', $b->id));

        $this->assertNotSame($first, session('temporary_password')['password']);
    }

    public function test_the_super_admin_cannot_reset_their_own_password_this_way(): void
    {
        $admin = $this->superAdmin();
        $before = $admin->password;

        $this->actingAs($admin)->post(route('admin.users.reset-password', $admin->id))
            ->assertSessionHas('error');

        $this->assertSame($before, $admin->fresh()->password);
    }

    public function test_a_counselor_cannot_reset_another_counselors_password(): void
    {
        $other = $this->counselor();

        $this->actingAs($this->counselor())->post(route('admin.users.reset-password', $other->id))->assertForbidden();
        $this->assertFalse($other->fresh()->must_change_password);
    }

    public function test_the_reset_button_shows_for_staff_rows_to_the_super_admin_only(): void
    {
        $admin = $this->superAdmin();
        $counselor = $this->counselor();
        $teacher = User::factory()->teacher()->create();

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->getContent();
        $this->assertStringContainsString('data-reset-password="' . $counselor->id . '"', $html);
        $this->assertStringNotContainsString('data-reset-password="' . $teacher->id . '"', $html);
        $this->assertStringNotContainsString('data-reset-password="' . $admin->id . '"', $html);

        $this->actingAs($counselor)->get(route('admin.users.index'))
            ->assertDontSee('data-reset-password=', false);
    }

    // ── Forced change ───────────────────────────────────────────────────

    public function test_a_reset_account_is_held_on_change_password_until_it_picks_one(): void
    {
        $counselor = $this->counselor(['must_change_password' => true]);

        $this->actingAs($counselor)->get(route('counselor.dashboard'))->assertRedirect(route('account.password.edit'));
        $this->actingAs($counselor)->get(route('admin.students.index'))->assertRedirect(route('account.password.edit'));
        $this->actingAs($counselor)->getJson(route('admin.notifications.poll'))->assertForbidden();

        $this->actingAs($counselor)->get(route('account.password.edit'))->assertOk()
            ->assertSee('data-password-forced', false)
            ->assertSee('Temporary password');
    }

    public function test_choosing_a_new_password_releases_the_account(): void
    {
        $counselor = $this->counselor(['password' => Hash::make('Temp12345678'), 'must_change_password' => true]);

        $this->actingAs($counselor)->put(route('password.update'), [
            'current_password' => 'Temp12345678',
            'password' => 'MyOwnPass2026',
            'password_confirmation' => 'MyOwnPass2026',
        ])->assertRedirect(route('dashboard'));

        $fresh = $counselor->fresh();
        $this->assertSame('Replaced their temporary password', \App\Models\AuditLog::where('action', 'user.password_changed')->sole()->description);
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check('MyOwnPass2026', $fresh->password));
        $this->actingAs($fresh)->get(route('counselor.dashboard'))->assertOk();
    }

    public function test_the_new_password_must_differ_and_have_letters_and_numbers(): void
    {
        $counselor = $this->counselor(['password' => Hash::make('Temp12345678'), 'must_change_password' => true]);

        $this->actingAs($counselor)->put(route('password.update'), [
            'current_password' => 'Temp12345678', 'password' => 'Temp12345678', 'password_confirmation' => 'Temp12345678',
        ])->assertSessionHasErrorsIn('updatePassword', ['password' => 'Choose a password different from your current one.']);

        $this->actingAs($counselor)->put(route('password.update'), [
            'current_password' => 'Temp12345678', 'password' => 'onlyletters', 'password_confirmation' => 'onlyletters',
        ])->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->assertTrue($counselor->fresh()->must_change_password);
    }

    // ── Sessions ────────────────────────────────────────────────────────

    public function test_a_password_change_signs_out_other_sessions(): void
    {
        $counselor = $this->counselor();

        // An existing session (stores the password hash on first request)…
        $this->actingAs($counselor)->get(route('counselor.dashboard'))->assertOk();

        // …the password changes somewhere else (e.g. the super admin resets it)…
        $counselor->forceFill(['password' => Hash::make('Changed2026')])->save();

        // …and that session is signed out on its next request.
        $this->get(route('counselor.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_changing_your_own_password_keeps_you_signed_in(): void
    {
        $counselor = $this->counselor(['password' => Hash::make('OldPass2026')]);

        $this->actingAs($counselor)->get(route('account.password.edit'))->assertOk();
        $this->put(route('password.update'), [
            'current_password' => 'OldPass2026', 'password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026',
        ])->assertSessionHasNoErrors();

        $this->get(route('counselor.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($counselor->fresh());
    }

    public function test_staff_have_a_change_password_link(): void
    {
        $this->actingAs($this->counselor())->get(route('counselor.dashboard'))
            ->assertSee('data-change-password-link', false)
            ->assertSee(route('account.password.edit'), false);
        $this->actingAs($this->superAdmin())->get(route('admin.users.index'))
            ->assertSee('data-change-password-link', false);
    }

    public function test_teachers_and_students_cannot_open_the_staff_password_page(): void
    {
        $this->actingAs(User::factory()->teacher()->create())->get(route('account.password.edit'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'student']))->get(route('account.password.edit'))->assertForbidden();
    }
}
