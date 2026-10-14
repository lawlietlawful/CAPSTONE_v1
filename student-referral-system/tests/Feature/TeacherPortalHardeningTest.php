<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * The Teacher Portal audit: the app's sign-in holds to the web login's
 * rules, sign-ins and password changes are logged, password changes sign
 * out other phones, and what a teacher sees about other people's casework
 * is limited to what they need.
 */
class TeacherPortalHardeningTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function activatedTeacher(array $attributes = []): User
    {
        return User::factory()->teacher()->create($attributes + [
            'username' => 'T-1001', 'password' => Hash::make('RightPass2026'), 'account_activated_at' => now(),
        ]);
    }

    private function appSignIn(string $id, string $password, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/teacher/login', ['email' => $id, 'password' => $password]);
    }

    // ── Sign-in ─────────────────────────────────────────────────────────

    public function test_five_wrong_passwords_pause_the_app_sign_in_with_a_countdown(): void
    {
        $this->activatedTeacher();

        for ($i = 0; $i < 4; $i++) {
            $this->appSignIn('T-1001', 'wrong-guess')->assertStatus(401);
        }
        $this->appSignIn('T-1001', 'wrong-guess')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '60')
            ->assertJson(['message' => 'Too many sign-in attempts. Please try again in 1 minute.', 'retry_after' => 60]);

        // Even the right password waits out the pause...
        $this->appSignIn('T-1001', 'RightPass2026')->assertStatus(429);

        // ...and then works.
        $this->travel(61)->seconds();
        $this->appSignIn('T-1001', 'RightPass2026')->assertOk()->assertJsonStructure(['token', 'teacher']);
    }

    public function test_the_pause_is_shared_with_the_website(): void
    {
        $this->activatedTeacher();
        for ($i = 0; $i < 5; $i++) {
            $this->appSignIn('T-1001', 'wrong-guess');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->post(route('login'), ['email' => 'T-1001', 'password' => 'RightPass2026'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unknown_unactivated_and_wrong_password_get_the_same_answer(): void
    {
        $this->activatedTeacher();
        User::factory()->teacher()->create(['username' => 'T-2002', 'account_activated_at' => null, 'password' => Hash::make('RightPass2026')]);

        $wrong = $this->appSignIn('T-1001', 'wrong-guess');
        $unknown = $this->appSignIn('T-9999', 'RightPass2026');
        $unactivated = $this->appSignIn('T-2002', 'RightPass2026');

        foreach ([$wrong, $unknown, $unactivated] as $response) {
            $response->assertStatus(401)->assertExactJson([
                'message' => 'Invalid Employee ID or password. Signing in for the first time? Activate your account first.',
            ]);
        }
    }

    public function test_an_unknown_id_still_checks_a_password_hash(): void
    {
        Cache::forever('login.dummy-hash.' . config('hashing.bcrypt.rounds'), Hash::make('anything'));
        Hash::partialMock()->shouldReceive('check')->once()->andReturnFalse();

        $this->appSignIn('T-9999', 'RightPass2026')->assertStatus(401);
    }

    public function test_one_device_cannot_try_ids_one_after_another(): void
    {
        $this->activatedTeacher();
        for ($i = 1; $i <= LoginThrottle::DEVICE_MAX_FAILURES; $i++) {
            $this->appSignIn("T-{$i}x", 'Password123');
        }

        $this->appSignIn('T-1001', 'RightPass2026')->assertStatus(429)
            ->assertJsonPath('message', 'Too many failed sign-ins from this device. Please try again in 15 minutes.');
        $this->appSignIn('T-1001', 'RightPass2026', ip: '10.0.0.2')->assertOk();
    }

    public function test_app_sign_ins_and_failures_are_in_the_activity_log(): void
    {
        $teacher = $this->activatedTeacher();

        $this->appSignIn('T-1001', 'wrong-guess');
        $this->appSignIn('T-1001', 'RightPass2026')->assertOk();

        $failed = AuditLog::where('action', 'auth.failed')->sole();
        $this->assertSame('Failed Teacher Portal sign-in attempt for T-1001', $failed->description);
        $this->assertSame($teacher->id, $failed->subject_id);
        $this->assertStringNotContainsString('wrong-guess', $failed->description);

        $login = AuditLog::where('action', 'auth.login')->sole();
        $this->assertSame($teacher->id, $login->user_id);
        $this->assertSame('Signed in to the Teacher Portal', $login->description);
    }

    public function test_student_app_sign_ins_are_logged_too(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['username' => '2026-0042', 'password' => Hash::make('RightPass2026'), 'account_activated_at' => now()]);

        $this->postJson('/api/login', ['email' => '2026-0042', 'password' => 'RightPass2026'])->assertOk();

        $this->assertSame('Signed in to the Student Portal', AuditLog::where('action', 'auth.login')->sole()->description);
    }

    public function test_activation_uses_the_same_password_rule_as_the_website(): void
    {
        $code = User::generateActivationCode();
        User::factory()->teacher()->create([
            'username' => 'T-3003', 'account_activated_at' => null,
            'activation_code' => Hash::make(User::canonicalActivationCode($code)),
        ]);

        $this->postJson('/api/teacher/activate', [
            'school_id' => 'T-3003', 'activation_code' => $code,
            'new_password' => 'lettersonly', 'new_password_confirmation' => 'lettersonly',
        ])->assertStatus(422)->assertJsonValidationErrors('new_password');

        $this->postJson('/api/teacher/activate', [
            'school_id' => 'T-3003', 'activation_code' => $code,
            'new_password' => 'Letters2026', 'new_password_confirmation' => 'Letters2026',
        ])->assertOk();

        $this->assertStringContainsString('Activated their account', AuditLog::where('action', 'auth.login')->sole()->description);
    }

    // ── Password change ─────────────────────────────────────────────────

    public function test_changing_the_password_signs_out_other_phones_and_is_logged(): void
    {
        $teacher = $this->activatedTeacher();
        $otherPhone = $teacher->createToken('teacher-portal');
        $thisPhone = $teacher->createToken('teacher-portal');

        $this->withToken($thisPhone->plainTextToken)->postJson('/api/teacher/change-password', [
            'current_password' => 'RightPass2026',
            'new_password' => 'NewerPass2027', 'new_password_confirmation' => 'NewerPass2027',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewerPass2027', $teacher->fresh()->password));
        $this->assertSame([$thisPhone->accessToken->id], $teacher->tokens()->pluck('id')->all());
        $this->assertSame('Changed their password in the Teacher Portal',
            AuditLog::where('action', 'user.password_changed')->sole()->description);
    }

    public function test_a_weak_new_password_is_refused(): void
    {
        Sanctum::actingAs($this->activatedTeacher());

        $this->postJson('/api/teacher/change-password', [
            'current_password' => 'RightPass2026',
            'new_password' => '12345678', 'new_password_confirmation' => '12345678',
        ])->assertStatus(422)->assertJsonValidationErrors('new_password');
    }

    public function test_the_student_app_password_change_follows_the_same_rules(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['password' => Hash::make('RightPass2026'), 'account_activated_at' => now()]);
        $other = $student->user->createToken('student-portal');
        $mine = $student->user->createToken('student-portal');

        $this->withToken($mine->plainTextToken)->postJson('/api/student/change-password', [
            'current_password' => 'RightPass2026', 'new_password' => 'lettersonly', 'new_password_confirmation' => 'lettersonly',
        ])->assertStatus(422);

        $this->withToken($mine->plainTextToken)->postJson('/api/student/change-password', [
            'current_password' => 'RightPass2026', 'new_password' => 'Student2027', 'new_password_confirmation' => 'Student2027',
        ])->assertOk();

        $this->assertSame([$mine->accessToken->id], $student->user->tokens()->pluck('id')->all());
    }

    // ── What a teacher sees about other people's casework ───────────────

    public function test_other_teachers_entries_show_what_and_when_but_not_what_they_wrote(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $colleague = User::factory()->teacher()->create();
        TeacherAssignment::factory()->create(['teacher_id' => $colleague->id, 'course' => 'BSIT', 'grade_level' => '1st Year', 'section' => 'A']);

        BehavioralReport::factory()->create(['student_id' => $student->id, 'reported_by' => $teacher->id,
            'incident_type' => 'Truancy', 'description' => 'Skipped my class twice.']);
        BehavioralReport::factory()->create(['student_id' => $student->id, 'reported_by' => $colleague->id,
            'incident_type' => 'Disciplinary Incident', 'description' => 'Confidential details from a colleague.']);
        Referral::factory()->create(['student_id' => $student->id, 'referred_by' => $colleague->id,
            'reason' => 'Private family matter shared in confidence.']);
        Sanctum::actingAs($teacher);

        $response = $this->getJson("/api/teacher/students/{$student->id}")->assertOk();
        $timeline = collect($response->json('timeline'));

        $mine = $timeline->firstWhere('mine', true);
        $this->assertSame('Skipped my class twice.', $mine['detail']);

        $theirs = $timeline->where('mine', false);
        $this->assertCount(2, $theirs);
        $this->assertTrue($theirs->every(fn ($e) => $e['detail'] === null));
        $this->assertContains('Disciplinary Incident', $theirs->pluck('title')->all());
        $response->assertDontSee('Confidential details from a colleague')
                 ->assertDontSee('Private family matter');
    }

    public function test_teachers_see_the_reason_without_system_tags_while_counselors_keep_them(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $counselor = User::factory()->create(['role' => 'admin', 'name' => 'Rosa Santos']);
        $referral = Referral::factory()->create([
            'student_id' => $student->id, 'referred_by' => $teacher->id, 'counselor_id' => $counselor->id,
            'reason' => '[AUTO-ESCALATED from Behavioral Report #33] [Flagged: description names violence/a weapon/a threat] He pushed a classmate.',
        ]);

        // The counselor's screen still explains the flag.
        $this->actingAs($counselor)->get(route('counselor.referrals.show', $referral))
            ->assertSee('[Flagged: description names violence/a weapon/a threat]', false);

        Sanctum::actingAs($teacher);
        $this->getJson("/api/teacher/referrals/{$referral->id}")->assertOk()
            ->assertJsonPath('referral.reason', 'He pushed a classmate.')
            ->assertJsonPath('referral.counselor_id', $counselor->id)
            ->assertJsonPath('referral.counselor_name', 'Rosa Santos');
        $this->getJson("/api/teacher/students/{$student->id}")->assertOk()
            ->assertJsonPath('timeline.0.detail', 'He pushed a classmate.');
    }

    public function test_the_dashboard_gives_the_profile_its_employee_id_and_advisory(): void
    {
        [$teacher] = $this->teacherAdvising();
        $teacher->update(['username' => 'T-2026-777']);
        \App\Models\Course::create(['name' => 'Bachelor of Science in Information Technology', 'education_level' => 'College']);
        TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'course' => 'Bachelor of Science in Information Technology',
            'grade_level' => '2nd Year', 'section' => 'Block 2']);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/dashboard')->assertOk()
            ->assertJsonPath('employee_id', 'T-2026-777')
            ->assertJsonPath('advisory', ['BSIT · 1st Year · A', 'BSIT · 2nd Year · Block 2']);
    }

    public function test_counselors_are_told_their_notes_stay_private(): void
    {
        $referral = Referral::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('counselor.referrals.show', $referral))
            ->assertSee('Only Guidance staff can see these notes.');
    }

    // ── Input limits ────────────────────────────────────────────────────

    public function test_incidents_cannot_be_dated_in_the_future(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $tomorrow = ['student_id' => $student->id, 'incident_type' => 'Truancy',
            'incident_date' => now()->addDay()->toDateString(), 'description' => 'Absent.'];

        // Website form (checked first: Sanctum::actingAs switches the guard).
        $this->actingAs($teacher)->post(route('teacher.behavioral-reports.store'), $tomorrow)
            ->assertSessionHasErrors('incident_date');

        Sanctum::actingAs($teacher);
        $this->postJson('/api/teacher/behavioral-reports', $tomorrow)
            ->assertStatus(422)->assertJsonPath('errors.incident_date.0', 'The incident date cannot be in the future.');

        $this->assertSame(0, BehavioralReport::count());
    }

    public function test_very_long_text_is_refused(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $counselor = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($teacher);
        $tooLong = str_repeat('a', 5001);

        $this->postJson('/api/teacher/behavioral-reports', [
            'student_id' => $student->id, 'incident_type' => 'Truancy',
            'incident_date' => now()->toDateString(), 'description' => $tooLong,
        ])->assertJsonValidationErrors('description');
        $this->postJson('/api/teacher/referrals', [
            'student_id' => $student->id, 'referral_type' => 'Absences', 'reason' => $tooLong,
        ])->assertJsonValidationErrors('reason');
        $this->postJson('/api/messages', ['receiver_id' => $counselor->id, 'content' => $tooLong])
            ->assertJsonValidationErrors('content');
    }
}
