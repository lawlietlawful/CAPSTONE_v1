<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Account deactivation (users.deactivated_at). Deleting a user is ON DELETE
 * CASCADE for everything they filed, so a departed teacher or counselor is
 * switched off instead: no sign-in anywhere, any open session ends, they drop
 * out of every "pick someone" list — and every record they filed stays.
 */
class AccountDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(array $attrs = []): User
    {
        return User::factory()->counselor()->create($attrs);
    }

    private function activatedTeacher(array $attrs = []): User
    {
        return User::factory()->teacher()->create(array_merge([
            'username' => 'T-' . fake()->unique()->numerify('####'),
            'password' => Hash::make('secret-pass'),
            'account_activated_at' => now(),
        ], $attrs));
    }

    // ── Deactivate / reactivate ──────────────────────────────────────────

    public function test_deactivating_keeps_every_record_they_filed(): void
    {
        $teacher = $this->activatedTeacher();
        $report = BehavioralReport::factory()->create(['reported_by' => $teacher->id]);
        $referral = Referral::factory()->create(['referred_by' => $teacher->id]);

        $this->actingAs($this->counselor())->post(route('admin.users.deactivate', $teacher->id))
            ->assertSessionHas('success');

        $this->assertTrue($teacher->fresh()->isDeactivated());
        $this->assertDatabaseHas('behavioral_reports', ['id' => $report->id, 'reported_by' => $teacher->id]);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'referred_by' => $teacher->id]);
    }

    public function test_deactivating_signs_them_out_of_the_portal_apps_immediately(): void
    {
        $teacher = $this->activatedTeacher();
        $teacher->createToken('teacher-portal');
        $this->assertSame(1, $teacher->tokens()->count());

        $this->actingAs($this->counselor())->post(route('admin.users.deactivate', $teacher->id));

        $this->assertSame(0, $teacher->tokens()->count());
    }

    public function test_reactivating_restores_the_same_account(): void
    {
        $teacher = $this->activatedTeacher(['deactivated_at' => now()]);

        $this->actingAs($this->counselor())->post(route('admin.users.reactivate', $teacher->id))
            ->assertSessionHas('success');

        $this->assertFalse($teacher->fresh()->isDeactivated());
        $this->postJson('/api/teacher/login', ['email' => $teacher->username, 'password' => 'secret-pass'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_you_cannot_deactivate_yourself(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('admin.users.deactivate', $superAdmin->id))
            ->assertSessionHas('error');

        $this->assertFalse($superAdmin->fresh()->isDeactivated());
    }

    public function test_student_accounts_are_not_deactivated_from_user_management(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $studentUser = Student::factory()->create()->user;

        $this->actingAs($superAdmin)->post(route('admin.users.deactivate', $studentUser->id))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Students page'));

        $this->assertFalse($studentUser->fresh()->isDeactivated());
    }

    // ── Sign-in is refused everywhere ────────────────────────────────────

    public function test_web_login_is_refused_with_the_reason(): void
    {
        $counselor = $this->counselor(['email' => 'off@school.com', 'password' => Hash::make('secret-pass'), 'deactivated_at' => now()]);

        $this->post('/login', ['email' => 'off@school.com', 'password' => 'secret-pass'])
            ->assertSessionHasErrors(['email' => User::DEACTIVATED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_a_wrong_password_on_a_deactivated_account_gets_the_generic_error(): void
    {
        // The reason is only revealed once the password matches, so it can't
        // be used to discover which accounts exist.
        $this->counselor(['email' => 'off@school.com', 'password' => Hash::make('secret-pass'), 'deactivated_at' => now()]);

        $errors = $this->post('/login', ['email' => 'off@school.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email')
            ->getSession()->get('errors')->get('email');

        $this->assertNotContains(User::DEACTIVATED_MESSAGE, $errors);
    }

    public function test_teacher_portal_login_is_refused(): void
    {
        $teacher = $this->activatedTeacher(['deactivated_at' => now()]);

        $this->postJson('/api/teacher/login', ['email' => $teacher->username, 'password' => 'secret-pass'])
            ->assertStatus(403)
            ->assertJson(['message' => User::DEACTIVATED_MESSAGE]);

        $this->assertSame(0, $teacher->tokens()->count());
    }

    public function test_teacher_activation_is_refused_and_the_code_is_not_consumed(): void
    {
        $code = User::generateActivationCode();
        $teacher = User::factory()->teacher()->create([
            'username' => 'T-9001',
            'activation_code' => Hash::make(User::canonicalActivationCode($code)),
            'deactivated_at' => now(),
        ]);

        $this->postJson('/api/teacher/activate', [
            'school_id' => 'T-9001', 'activation_code' => $code,
            'new_password' => 'new-password1', 'new_password_confirmation' => 'new-password1',
        ])->assertStatus(403)->assertJson(['message' => User::DEACTIVATED_MESSAGE]);

        $this->assertNotNull($teacher->fresh()->activation_code);
        $this->assertNull($teacher->fresh()->account_activated_at);
    }

    public function test_student_portal_login_is_refused(): void
    {
        $student = Student::factory()->create();
        $student->user->update([
            'password' => Hash::make('secret-pass'), 'account_activated_at' => now(), 'deactivated_at' => now(),
        ]);

        $this->postJson('/api/login', ['email' => $student->user->username ?? $student->user->email, 'password' => 'secret-pass'])
            ->assertStatus(403)
            ->assertJson(['message' => User::DEACTIVATED_MESSAGE]);
    }

    // ── An already-open session ends ─────────────────────────────────────

    public function test_an_open_web_session_is_ended_on_the_next_request(): void
    {
        $counselor = $this->counselor();
        $this->actingAs($counselor)->get(route('admin.users.index'))->assertOk();

        $counselor->update(['deactivated_at' => now()]);

        $this->actingAs($counselor)->get(route('admin.users.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => User::DEACTIVATED_MESSAGE]);
        $this->assertGuest();
    }

    public function test_a_portal_token_issued_before_deactivation_stops_working(): void
    {
        // Covers the gap deactivate()'s token revocation can't: a deactivation
        // made any other way still cuts the portal off on its next call.
        $teacher = $this->activatedTeacher();
        $token = $teacher->createToken('teacher-portal')->plainTextToken;
        $this->withToken($token)->getJson('/api/teacher/dashboard')->assertOk();

        $teacher->update(['deactivated_at' => now()]);
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/teacher/dashboard')
            ->assertStatus(401)
            ->assertJson(['message' => User::DEACTIVATED_MESSAGE]);
    }

    // ── They drop out of every "pick someone" list ───────────────────────

    public function test_new_assignment_dropdowns_hide_a_deactivated_counselor(): void
    {
        $me = $this->counselor();
        $this->counselor(['name' => 'Active Colleague']);
        $this->counselor(['name' => 'Gone Colleague', 'deactivated_at' => now()]);

        $html = $this->actingAs($me)->get(route('counselor.referrals.create'))->getContent();

        $this->assertStringContainsString('Active Colleague', $html);
        $this->assertStringNotContainsString('Gone Colleague', $html);
    }

    public function test_a_referral_still_assigned_to_a_deactivated_counselor_keeps_them_selected(): void
    {
        // Dropping them from this dropdown would make the next save of the
        // form silently unassign the case.
        $viewer = User::factory()->create(['role' => 'super_admin']);
        $gone = $this->counselor(['name' => 'Gone Colleague', 'deactivated_at' => now()]);
        $this->counselor(['name' => 'Other Gone', 'deactivated_at' => now()]);
        $referral = Referral::factory()->create(['counselor_id' => $gone->id]);

        $html = $this->actingAs($viewer)->get(route('admin.referrals.show', $referral))->getContent();

        $this->assertMatchesRegularExpression('#<option value="' . $gone->id . '" selected>Gone Colleague \(deactivated\)</option>#', $html);
        $this->assertStringNotContainsString('Other Gone', $html, 'only the current assignee is kept');
    }

    public function test_the_referral_counselor_filter_still_lists_deactivated_counselors(): void
    {
        // Their past referrals still need to be findable.
        $viewer = User::factory()->create(['role' => 'super_admin']);
        $this->counselor(['name' => 'Gone Colleague', 'deactivated_at' => now()]);

        $html = $this->actingAs($viewer)->get(route('admin.referrals.index'))->getContent();

        $this->assertStringContainsString('Gone Colleague (deactivated)', $html);
    }

    public function test_a_new_referral_cannot_be_assigned_to_a_deactivated_counselor(): void
    {
        $me = $this->counselor();
        $gone = $this->counselor(['deactivated_at' => now()]);
        $student = Student::factory()->create();

        $this->actingAs($me)->post(route('counselor.referrals.store'), [
            'student_id' => $student->id,
            'referral_type' => Referral::REFERRAL_TYPES[0],
            'reason' => 'Needs follow-up.',
            'counselor_id' => $gone->id,
        ])->assertSessionHasErrors('counselor_id');

        $this->assertDatabaseMissing('referrals', ['counselor_id' => $gone->id]);
    }

    public function test_saving_a_referral_with_its_existing_deactivated_assignee_is_allowed(): void
    {
        $me = $this->counselor();
        $gone = $this->counselor(['deactivated_at' => now()]);
        $referral = Referral::factory()->create(['counselor_id' => $gone->id, 'status' => 'pending']);

        $this->actingAs($me)->patch(route('counselor.referrals.updateStatus', $referral), [
            'status' => 'in_progress', 'counselor_id' => $gone->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('in_progress', $referral->fresh()->status);
        $this->assertSame($gone->id, $referral->fresh()->counselor_id);
    }

    public function test_reassigning_a_referral_to_a_different_deactivated_counselor_is_refused(): void
    {
        $me = $this->counselor();
        $gone = $this->counselor(['deactivated_at' => now()]);
        $referral = Referral::factory()->create(['counselor_id' => $me->id, 'status' => 'pending']);

        $this->actingAs($me)->patch(route('counselor.referrals.updateStatus', $referral), [
            'status' => 'pending', 'counselor_id' => $gone->id,
        ])->assertSessionHasErrors('counselor_id');

        $this->assertSame($me->id, $referral->fresh()->counselor_id);
    }

    public function test_the_sole_active_counselor_is_still_the_automatic_default(): void
    {
        $active = $this->counselor();
        $this->counselor(['deactivated_at' => now()]);

        $this->assertSame($active->id, User::soleCounselorId());
    }

    public function test_deactivated_counselors_get_no_new_referral_alerts(): void
    {
        $active = $this->counselor();
        $gone = $this->counselor(['deactivated_at' => now()]);
        $referral = Referral::factory()->create(['counselor_id' => null]);

        app(\App\Services\NotificationService::class)->newPendingReferral($referral);

        $this->assertTrue(Notification::where('user_id', $active->id)->exists());
        $this->assertFalse(Notification::where('user_id', $gone->id)->exists());
    }

    // ── Messaging ────────────────────────────────────────────────────────

    public function test_a_deactivated_account_cannot_be_sent_a_new_notice(): void
    {
        $counselor = $this->counselor();
        $teacher = $this->activatedTeacher(['deactivated_at' => now()]);

        $this->assertFalse(Message::canInitiate($counselor, $teacher));
        $this->actingAs($counselor)->post(route('counselor.messages.store'), [
            'receiver_id' => $teacher->id, 'content' => 'Hello',
        ])->assertForbidden();
    }

    public function test_compose_lists_and_the_counselors_endpoint_skip_deactivated_accounts(): void
    {
        $counselor = $this->counselor();
        $this->activatedTeacher(['name' => 'Gone Teacher', 'deactivated_at' => now()]);
        $this->counselor(['name' => 'Gone Counselor', 'deactivated_at' => now()]);

        $compose = $this->actingAs($counselor)->get(route('counselor.messages.index'))->getContent();
        $this->assertStringNotContainsString('Gone Teacher', $compose);

        $teacher = $this->activatedTeacher();
        $names = collect($this->actingAs($teacher, 'sanctum')->getJson('/api/counselors')->json('data'))->pluck('name');
        $this->assertNotContains('Gone Counselor', $names->all());
    }

    // ── How it shows on the admin pages ──────────────────────────────────

    public function test_user_management_shows_the_badge_and_offers_reactivate(): void
    {
        $teacher = $this->activatedTeacher(['deactivated_at' => now()]);

        $html = $this->actingAs($this->counselor())->get(route('admin.users.index'))->getContent();

        $this->assertStringContainsString('data-deactivated-badge', $html);
        $this->assertStringContainsString('data-reactivate-user="' . $teacher->id . '"', $html);
        $this->assertStringNotContainsString('data-deactivate-user="' . $teacher->id . '"', $html);
    }

    public function test_user_management_status_filter(): void
    {
        $counselor = $this->counselor();
        $this->activatedTeacher(['name' => 'Still Here']);
        $this->activatedTeacher(['name' => 'Gone Teacher', 'deactivated_at' => now()]);

        $names = fn ($status) => $this->actingAs($counselor)->get(route('admin.users.index', ['status' => $status]))
            ->viewData('users')->pluck('name')->all();

        $this->assertSame(['Gone Teacher'], $names('deactivated'));
        $this->assertSame(['Still Here'], $names('active'));
    }

    public function test_teachers_page_counts_filters_and_badges_deactivated_teachers_separately(): void
    {
        $counselor = $this->counselor();
        User::factory()->teacher()->create(['name' => 'Pending Teacher']);
        User::factory()->teacher()->create(['name' => 'Gone Teacher', 'deactivated_at' => now()]);

        $page = $this->actingAs($counselor)->get(route('admin.teachers.index'));
        $this->assertSame(1, $page->viewData('pendingActivationCount'), 'a deactivated account is not "pending activation"');
        $this->assertStringContainsString('data-deactivated-badge', $page->getContent());

        $pending = $this->actingAs($counselor)->get(route('admin.teachers.index', ['status' => 'pending']))
            ->viewData('teachers')->pluck('name')->all();
        $this->assertSame(['Pending Teacher'], $pending);

        $deactivated = $this->actingAs($counselor)->get(route('admin.teachers.index', ['status' => 'deactivated']))
            ->viewData('teachers')->pluck('name')->all();
        $this->assertSame(['Gone Teacher'], $deactivated);
    }

    public function test_teachers_page_offers_no_activation_code_or_reset_for_a_deactivated_teacher(): void
    {
        $teacher = User::factory()->teacher()->create(['deactivated_at' => now()]);

        $html = $this->actingAs($this->counselor())->get(route('admin.teachers.index'))->getContent();

        $this->assertStringNotContainsString('id="regen-code-' . $teacher->id . '"', $html);
        $this->assertStringNotContainsString('id="reset-password-' . $teacher->id . '"', $html);
        $this->assertStringContainsString('reactivate in User Management to restore access', $html);
    }

    public function test_the_csv_export_reports_each_teachers_account_status(): void
    {
        $counselor = $this->counselor();
        $this->activatedTeacher(['name' => 'Active Teacher']);
        User::factory()->teacher()->create(['name' => 'Pending Teacher']);
        $this->activatedTeacher(['name' => 'Gone Teacher', 'deactivated_at' => now()]);

        $csv = $this->actingAs($counselor)->get(route('admin.teachers.export'))->streamedContent();
        $status = fn ($name) => str_getcsv(collect(explode("\n", $csv))->first(fn ($l) => str_contains($l, $name)))[7];

        $this->assertStringContainsString('Account Status', $csv);
        $this->assertSame('Active', $status('Active Teacher'));
        $this->assertSame('Pending Activation', $status('Pending Teacher'));
        $this->assertSame('Deactivated', $status('Gone Teacher'));
    }

    public function test_teacher_profile_explains_the_deactivation_and_disables_send_notice(): void
    {
        $teacher = $this->activatedTeacher(['deactivated_at' => now()]);

        $html = $this->actingAs($this->counselor())->get(route('admin.teachers.show', $teacher->id))->getContent();

        $this->assertStringContainsString('data-deactivated-banner', $html);
        $this->assertStringContainsString('data-send-notice-disabled', $html);
        $this->assertStringNotContainsString("\$dispatch('open-messages', { to: {$teacher->id} })", $html);
    }

    public function test_register_a_teacher_opens_the_add_modal_on_user_management(): void
    {
        // The Teachers page's empty state used to link to the removed create page.
        $counselor = $this->counselor();

        $teachersHtml = $this->actingAs($counselor)->get(route('admin.teachers.index'))->getContent();
        $this->assertStringContainsString(route('admin.users.index', ['add' => 1]), $teachersHtml);

        $usersHtml = $this->actingAs($counselor)->get(route('admin.users.index', ['add' => 1]))->getContent();
        $this->assertStringContainsString('showAddUser: true', $usersHtml);
    }
}
