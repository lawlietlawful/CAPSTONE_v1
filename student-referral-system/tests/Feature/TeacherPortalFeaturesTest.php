<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\Seminar;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * Teacher Portal features: the duplicate-referral warning, the teacher's
 * follow-up after a referral is resolved, and the advisees' upcoming
 * seminars.
 */
class TeacherPortalFeaturesTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Rosa Santos']);
    }

    // ── Duplicate referral warning ──────────────────────────────────────

    public function test_a_second_referral_for_a_student_with_an_open_one_asks_first(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $colleague = User::factory()->teacher()->create();
        $counselor = $this->counselor();
        $open = Referral::factory()->create([
            'student_id' => $student->id, 'referred_by' => $colleague->id, 'counselor_id' => $counselor->id,
            'status' => 'in_progress', 'referral_type' => 'Absences', 'reason' => 'Private reason from a colleague.',
        ]);
        Sanctum::actingAs($teacher);

        $response = $this->postJson('/api/teacher/referrals', [
            'student_id' => $student->id, 'referral_type' => 'Tardiness', 'reason' => 'Late every day.',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('existing.id', $open->id)
            ->assertJsonPath('existing.mine', false)
            ->assertJsonPath('existing.status', 'in_progress')
            ->assertJsonPath('existing.counselor_id', $counselor->id)
            ->assertJsonPath('existing.counselor_name', 'Rosa Santos')
            ->assertDontSee('Private reason from a colleague');
        $this->assertSame(1, Referral::count());
    }

    public function test_the_teacher_can_file_it_anyway(): void
    {
        $this->fakeMlEngine('low', 20);
        [$teacher, $student] = $this->teacherAdvising();
        Referral::factory()->create(['student_id' => $student->id, 'status' => 'pending']);
        Sanctum::actingAs($teacher);

        $this->postJson('/api/teacher/referrals', [
            'student_id' => $student->id, 'referral_type' => 'Tardiness', 'reason' => 'Late every day.',
            'confirm_duplicate' => true,
        ])->assertCreated();

        $this->assertSame(2, Referral::count());
    }

    public function test_resolved_or_cancelled_referrals_do_not_count_as_open(): void
    {
        $this->fakeMlEngine('low', 20);
        [$teacher, $student] = $this->teacherAdvising();
        Referral::factory()->create(['student_id' => $student->id, 'status' => 'resolved', 'resolved_at' => now()]);
        Referral::factory()->create(['student_id' => $student->id, 'status' => 'cancelled']);
        Sanctum::actingAs($teacher);

        $this->postJson('/api/teacher/referrals', [
            'student_id' => $student->id, 'referral_type' => 'Absences', 'reason' => 'Absent again.',
        ])->assertCreated();
    }

    // ── Follow-up after resolution ──────────────────────────────────────

    private function resolvedReferral(User $teacher, Student $student, ?User $counselor = null): Referral
    {
        return Referral::factory()->create([
            'student_id' => $student->id, 'referred_by' => $teacher->id,
            'counselor_id' => ($counselor ?? $this->counselor())->id,
            'status' => 'resolved', 'resolved_at' => now(),
        ]);
    }

    public function test_the_teacher_reports_how_the_student_is_doing_and_the_counselor_is_told(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $counselor = $this->counselor();
        $referral = $this->resolvedReferral($teacher, $student, $counselor);
        Sanctum::actingAs($teacher);

        $this->postJson("/api/teacher/referrals/{$referral->id}/follow-up", [
            'outcome' => 'improved', 'note' => 'Attending every class now.',
        ])->assertOk()
            ->assertJsonPath('referral.follow_up.outcome', 'improved')
            ->assertJsonPath('referral.follow_up.label', 'Improved')
            ->assertJsonPath('referral.follow_up.note', 'Attending every class now.');

        $referral->refresh();
        $this->assertSame('improved', $referral->teacher_followup_outcome);
        $this->assertNotNull($referral->teacher_followup_at);

        $notice = Notification::where('user_id', $counselor->id)->where('type', 'teacher_followup')->sole();
        $this->assertStringContainsString('has improved in class', $notice->message);
        $this->assertSame(1, AuditLog::where('action', 'referral.followup')->count());

        // The counselor sees it on the referral.
        $this->actingAs($counselor)->get(route('counselor.referrals.show', $referral))
            ->assertSee('data-teacher-followup', false)
            ->assertSee('Attending every class now.');
    }

    public function test_a_follow_up_waits_for_resolution(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $referral = Referral::factory()->create(['student_id' => $student->id, 'referred_by' => $teacher->id, 'status' => 'in_progress']);
        Sanctum::actingAs($teacher);

        $this->postJson("/api/teacher/referrals/{$referral->id}/follow-up", ['outcome' => 'improved'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You can add a follow-up once Guidance resolves this referral.');
    }

    public function test_only_the_referring_teacher_can_follow_up_and_only_with_a_known_outcome(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $referral = $this->resolvedReferral($teacher, $student);

        Sanctum::actingAs(User::factory()->teacher()->create());
        $this->postJson("/api/teacher/referrals/{$referral->id}/follow-up", ['outcome' => 'improved'])->assertForbidden();

        Sanctum::actingAs($teacher);
        $this->postJson("/api/teacher/referrals/{$referral->id}/follow-up", ['outcome' => 'great'])
            ->assertJsonValidationErrors('outcome');
        $this->assertNull($referral->fresh()->teacher_followup_outcome);
    }

    // ── Advisees' upcoming seminars ─────────────────────────────────────

    private function seminar(array $overrides = []): Seminar
    {
        return Seminar::create($overrides + [
            'title' => 'Study Habits', 'date' => now()->addDays(3)->toDateString(), 'time' => '10:00:00',
            'venue' => 'AVR', 'is_required' => true, 'trigger_reason' => 'general', 'status' => 'upcoming',
        ]);
    }

    public function test_upcoming_seminars_list_only_my_enrolled_advisees_in_the_next_two_weeks(): void
    {
        [$teacher, $ana] = $this->teacherAdvising(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        $ben = Student::factory()->create(['first_name' => 'Ben', 'last_name' => 'Reyes']);   // also advised
        $outsider = Student::factory()->create(['course' => 'BSN', 'first_name' => 'Out', 'last_name' => 'Sider']);

        $soon = $this->seminar(['title' => 'Study Habits']);
        $soon->students()->attach([
            $ana->id => ['status' => 'enrolled'],
            $ben->id => ['status' => 'excused'],         // not going
            $outsider->id => ['status' => 'enrolled'],   // not my advisee
        ]);
        $this->seminar(['title' => 'Far Away', 'date' => now()->addDays(20)->toDateString()])
            ->students()->attach($ana->id, ['status' => 'enrolled']);
        $this->seminar(['title' => 'Called Off', 'status' => 'cancelled'])
            ->students()->attach($ana->id, ['status' => 'enrolled']);
        $this->seminar(['title' => 'Nobody Mine'])
            ->students()->attach($outsider->id, ['status' => 'enrolled']);
        Sanctum::actingAs($teacher);

        $response = $this->getJson('/api/teacher/seminars/upcoming')->assertOk();

        $this->assertSame(['Study Habits'], collect($response->json('seminars'))->pluck('title')->all());
        $this->assertSame(['Cruz, Ana'], collect($response->json('seminars.0.students'))->pluck('name')->all());
        $response->assertDontSee('Sider');
    }

    public function test_a_teacher_with_no_advisory_gets_no_seminars(): void
    {
        $this->seminar()->students()->attach(Student::factory()->create()->id, ['status' => 'enrolled']);
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->getJson('/api/teacher/seminars/upcoming')->assertOk()->assertExactJson(['seminars' => []]);
    }
}
