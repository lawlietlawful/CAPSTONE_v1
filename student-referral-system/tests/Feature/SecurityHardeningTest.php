<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BehavioralReport;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Security pass on the super admin / counselor split:
 *
 *  - Counselors could use the super admin's referral pages (no role check):
 *    delete any referral, rewrite or take a colleague's case, bulk-reassign.
 *    Shared pages even linked them there. Analytics and SMS Logs (every
 *    parent's number) were only hidden from the sidebar.
 *  - No audit trail; SMS API key stored in plain text and printed into the
 *    Settings page; parents' numbers and messages written to laravel.log;
 *    no security headers; anyone could self-register.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'name' => 'Head Admin']);
    }

    private function counselor(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'admin']);
    }

    // ── The super admin's pages are theirs ──────────────────────────────

    public static function superAdminOnlyPages(): array
    {
        return [
            'referrals'        => ['admin.referrals.index'],
            'referral export'  => ['admin.referrals.export'],
            'seminars'         => ['admin.seminars.index'],
            'behavioral rpts'  => ['admin.behavioral-reports.index'],
            'analytics'        => ['admin.analytics.index'],
            'sms logs'         => ['admin.sms-logs.index'],
            'activity log'     => ['admin.activity.index'],
            'settings'         => ['admin.settings.index'],
        ];
    }

    #[DataProvider('superAdminOnlyPages')]
    public function test_a_counselor_cannot_open_the_super_admins_pages(string $route): void
    {
        $this->actingAs($this->counselor())->get(route($route))->assertForbidden();
        $this->actingAs($this->superAdmin())->get(route($route))->assertSuccessful();
    }

    public function test_a_counselor_cannot_delete_rewrite_or_bulk_reassign_through_the_admin_routes(): void
    {
        $owner = $this->counselor();
        $other = $this->counselor();
        $a = Referral::factory()->create(['counselor_id' => $owner->id, 'status' => 'in_progress']);
        $b = Referral::factory()->create(['counselor_id' => $owner->id]);

        $this->actingAs($other)->put(route('admin.referrals.update', $a), [
            'referral_type' => $a->referral_type, 'reason' => 'x', 'priority' => 'low', 'status' => 'resolved', 'counselor_id' => $other->id,
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('admin.referrals.destroy', $b))->assertForbidden();
        $this->actingAs($other)->post(route('admin.referrals.bulkAction'), [
            'referral_ids' => [$a->id, $b->id], 'action' => 'delete',
        ])->assertForbidden();

        $this->assertSame(['in_progress', $owner->id], [$a->fresh()->status, $a->fresh()->counselor_id]);
        $this->assertNotNull($b->fresh());
    }

    public function test_shared_pages_send_each_role_to_its_own_referral_pages(): void
    {
        $student = Student::factory()->create();
        $referral = Referral::factory()->create(['student_id' => $student->id]);

        $this->actingAs($this->counselor())->get(route('admin.students.show', $student))
            ->assertSee(route('counselor.referrals.show', $referral), false)
            ->assertSee(route('counselor.referrals.create', ['student_id' => $student->id]), false)
            ->assertDontSee(route('admin.referrals.show', $referral), false);

        $this->actingAs($this->superAdmin())->get(route('admin.students.show', $student))
            ->assertSee(route('admin.referrals.show', $referral), false);
    }

    public function test_the_at_risk_refer_form_posts_to_the_counselors_own_endpoint(): void
    {
        $student = Student::factory()->create();
        RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 80, 'risk_level' => 'high', 'assessed_at' => now()]);

        $this->actingAs($this->counselor())->get(route('admin.risk.show', $student))
            ->assertSee('action="' . route('counselor.referrals.store') . '"', false)
            ->assertDontSee('action="' . route('admin.referrals.store') . '"', false);
    }

    public function test_the_counselor_referral_form_preselects_a_student_from_the_link(): void
    {
        $student = Student::factory()->create(['status' => 'active']);

        $this->actingAs($this->counselor())->get(route('counselor.referrals.create', ['student_id' => $student->id]))
            ->assertSee("selected: '{$student->id}'", false);
    }

    public function test_at_risk_bulk_assign_skips_a_colleagues_open_case(): void
    {
        $owner = $this->counselor();
        $me = $this->counselor();
        $student = Student::factory()->create();
        $assessment = RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 80, 'risk_level' => 'high', 'assessed_at' => now()]);
        $open = Referral::factory()->create(['student_id' => $student->id, 'counselor_id' => $owner->id, 'status' => 'pending']);

        $this->actingAs($me)->post(route('admin.risk.bulkAction'), [
            'assessment_ids' => [$assessment->id], 'action' => 'assign_counselor', 'assign_counselor_id' => $me->id,
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'open case belongs to another counselor'));

        $this->assertSame($owner->id, $open->fresh()->counselor_id);
    }

    public function test_the_super_admins_bulk_assign_notifies_and_audits_each_reassignment(): void
    {
        $from = $this->counselor();
        $to = $this->counselor(['name' => 'Sir Cruz']);
        $referrals = Referral::factory()->count(2)->create(['counselor_id' => $from->id]);

        $this->actingAs($this->superAdmin())->post(route('admin.referrals.bulkAction'), [
            'referral_ids' => $referrals->pluck('id')->all(), 'action' => 'assign_counselor', 'assign_counselor_id' => $to->id,
        ])->assertSessionHas('success');

        $this->assertSame(2, Notification::where('user_id', $to->id)->where('type', 'referral_assigned')->count());
        $this->assertSame(2, AuditLog::where('action', 'referral.reassigned')->count());
        $this->assertStringContainsString('to Sir Cruz', AuditLog::where('action', 'referral.reassigned')->first()->description);
    }

    // ── Audit trail ─────────────────────────────────────────────────────

    public function test_deleting_a_referral_is_recorded_with_who_and_what(): void
    {
        $student = Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        $referral = Referral::factory()->create(['student_id' => $student->id]);

        $this->actingAs($this->superAdmin())->delete(route('admin.referrals.destroy', $referral));

        $log = AuditLog::where('action', 'referral.deleted')->sole();
        $this->assertSame('Head Admin', $log->user_name);
        $this->assertSame(['Referral', $referral->id], [$log->subject_type, $log->subject_id]);
        $this->assertStringContainsString("Deleted referral #{$referral->id} (Ana Cruz", $log->description);
        $this->assertSame('127.0.0.1', $log->ip_address);
    }

    public function test_status_changes_and_reassignments_are_recorded(): void
    {
        $me = $this->counselor();
        $colleague = $this->counselor(['name' => 'Sir Cruz']);
        $referral = Referral::factory()->create(['counselor_id' => $me->id, 'status' => 'pending']);

        $this->actingAs($me)->patch(route('counselor.referrals.updateStatus', $referral), [
            'status' => 'in_progress', 'counselor_id' => $colleague->id,
        ]);

        $this->assertStringContainsString('status: pending → in progress', AuditLog::where('action', 'referral.status')->sole()->description);
        $this->assertStringContainsString('to Sir Cruz', AuditLog::where('action', 'referral.reassigned')->sole()->description);
    }

    public function test_account_changes_are_recorded(): void
    {
        $admin = $this->superAdmin();
        $counselor = $this->counselor(['name' => 'Sir Cruz']);

        $this->actingAs($admin)->post(route('admin.users.reset-password', $counselor->id));
        $this->actingAs($admin)->post(route('admin.users.deactivate', $counselor->id));
        $this->actingAs($admin)->post(route('admin.users.reactivate', $counselor->id));

        $this->assertSame(
            ['user.password_reset', 'user.deactivated', 'user.reactivated'],
            AuditLog::orderBy('id')->pluck('action')->all()
        );
        // The temporary password itself is never written to the log.
        $this->assertStringNotContainsString(session('temporary_password')['password'] ?? 'x', AuditLog::pluck('description')->implode(' '));
    }

    public function test_sign_ins_and_failed_attempts_are_recorded(): void
    {
        $user = $this->counselor(['email' => 'cruz@school.test', 'password' => bcrypt('RightPass2026')]);

        $this->post(route('login'), ['email' => 'cruz@school.test', 'password' => 'wrong-guess']);
        $this->post(route('login'), ['email' => 'cruz@school.test', 'password' => 'RightPass2026']);

        $failed = AuditLog::where('action', 'auth.failed')->sole();
        $this->assertSame('Failed sign-in attempt for cruz@school.test', $failed->description);
        $this->assertNull($failed->user_id);
        $this->assertStringNotContainsString('wrong-guess', $failed->description);
        $this->assertSame($user->id, AuditLog::where('action', 'auth.login')->sole()->user_id);
    }

    public function test_settings_changes_record_names_never_values(): void
    {
        $this->actingAs($this->superAdmin())->post(route('admin.settings.update'), [
            'sms_sender_name' => 'MU-GUIDANCE', 'sms_api_key' => 'sk_live_secret_123',
        ]);

        $log = AuditLog::where('action', 'settings.updated')->sole();
        $this->assertStringContainsString('SMS API key replaced', $log->description);
        $this->assertStringContainsString('sms_sender_name', $log->description);
        $this->assertStringNotContainsString('sk_live_secret_123', $log->description);
        $this->assertStringNotContainsString('MU-GUIDANCE', $log->description);
    }

    public function test_the_activity_log_lists_and_filters_entries(): void
    {
        $admin = $this->superAdmin();
        AuditLog::record('referral.deleted', null, 'Deleted referral #7 (Ana Cruz)', $admin);
        AuditLog::record('auth.failed', null, 'Failed sign-in attempt for x@y.test', null);

        $this->actingAs($admin)->get(route('admin.activity.index'))->assertOk()
            ->assertSee('Deleted referral #7 (Ana Cruz)')
            ->assertSee('data-failed-today', false);

        $this->actingAs($admin)->get(route('admin.activity.index', ['group' => 'referral']))
            ->assertSee('Deleted referral #7')
            ->assertDontSee('Failed sign-in attempt for x@y.test');
    }

    // ── Secrets, logs, headers ──────────────────────────────────────────

    public function test_the_sms_api_key_is_encrypted_and_never_printed_back(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.settings.update'), ['sms_api_key' => 'sk_live_secret_123']);

        $raw = Setting::where('key', 'sms_api_key')->value('value');
        $this->assertNotSame('sk_live_secret_123', $raw);
        $this->assertSame('encrypted', Setting::where('key', 'sms_api_key')->value('type'));
        $this->assertSame('sk_live_secret_123', Setting::get('sms_api_key'));

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertDontSee('sk_live_secret_123')
            ->assertDontSee($raw)
            ->assertSee('data-sms-key-saved', false);

        // Blank keeps it; the checkbox removes it.
        $this->actingAs($admin)->post(route('admin.settings.update'), ['sms_api_key' => '']);
        $this->assertSame('sk_live_secret_123', Setting::get('sms_api_key'));
        $this->actingAs($admin)->post(route('admin.settings.update'), ['clear_sms_api_key' => '1']);
        $this->assertSame('', (string) Setting::get('sms_api_key', ''));
    }

    public function test_settings_are_read_once_per_request(): void
    {
        Setting::set('sms_sender_name', 'MU');
        \DB::enableQueryLog();
        foreach (range(1, 5) as $i) {
            Setting::get('sms_sender_name');
        }
        $this->assertCount(1, collect(\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `settings`')));

        Setting::set('sms_sender_name', 'MU2');   // saving refreshes it
        $this->assertSame('MU2', Setting::get('sms_sender_name'));
    }

    public function test_simulated_sms_never_writes_the_number_or_message_to_the_log(): void
    {
        $student = Student::factory()->create();
        Log::spy();

        app(SmsService::class)->sendSms('09171234567', 'Private message body', $student->id, 'Parent', 'parent');

        Log::shouldHaveReceived('info')->withArgs(fn ($line) => str_contains($line, '0917•••••67')
            && ! str_contains($line, '09171234567') && ! str_contains($line, 'Private message body'))->once();
        // The full record stays in sms_logs (super admin only).
        $this->assertDatabaseHas('sms_logs', ['recipient_number' => '09171234567', 'message' => 'Private message body']);
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
