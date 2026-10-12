<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Counselor casework (Referrals + Interventions):
 *
 *  - counselor.referrals edit/update/destroy were registered with no
 *    controller methods behind them, so those URLs 500'd.
 *  - Reassigning a referral never told the counselor it was handed to.
 *  - Any counselor could change status/notes/assignment of a colleague's
 *    case, or log a session on it (which also claims it).
 *  - Referral form fields had no border, and no way to search students.
 *  - Validation said "The student id field is required.".
 */
class CounselorCaseworkTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'admin']);
    }

    // ── Routes ──────────────────────────────────────────────────────────

    public function test_there_are_no_dead_counselor_referral_routes(): void
    {
        foreach (['edit', 'update', 'destroy'] as $action) {
            $this->assertFalse(Route::has("counselor.referrals.{$action}"), $action);
        }

        $referral = Referral::factory()->create();
        $counselor = $this->counselor();
        $this->actingAs($counselor)->get("/counselor/referrals/{$referral->id}/edit")->assertNotFound();
        $this->actingAs($counselor)->put("/counselor/referrals/{$referral->id}")->assertStatus(405);
        $this->actingAs($counselor)->delete("/counselor/referrals/{$referral->id}")->assertStatus(405);
        $this->assertNotNull($referral->fresh());
    }

    // ── Reassignment notifications ──────────────────────────────────────

    public function test_reassigning_a_referral_notifies_the_new_counselor(): void
    {
        $me = $this->counselor(['name' => 'Ma\'am Edago']);
        $colleague = $this->counselor(['name' => 'Sir Cruz']);
        $referral = Referral::factory()->create(['counselor_id' => $me->id, 'status' => 'pending']);

        $this->actingAs($me)->patch(route('counselor.referrals.updateStatus', $referral), [
            'status' => 'pending', 'counselor_id' => $colleague->id,
        ])->assertSessionHas('success');

        $note = Notification::where('user_id', $colleague->id)->where('type', 'referral_assigned')->sole();
        $this->assertSame('Referral assigned to you', $note->title);
        $this->assertStringContainsString("was assigned to you by Ma'am Edago.", $note->message);
        $this->assertSame($referral->id, $note->reference_id);
    }

    public function test_no_assignment_notice_for_yourself_or_an_unchanged_assignee(): void
    {
        $me = $this->counselor();
        $unassigned = Referral::factory()->create(['counselor_id' => null, 'status' => 'pending']);
        $mine = Referral::factory()->create(['counselor_id' => $me->id, 'status' => 'pending']);

        // Claiming an unassigned case, then saving my own case unchanged.
        $this->actingAs($me)->patch(route('counselor.referrals.updateStatus', $unassigned), ['status' => 'in_progress', 'counselor_id' => $me->id]);
        $this->actingAs($me)->patch(route('counselor.referrals.updateStatus', $mine), ['status' => 'in_progress', 'counselor_id' => $me->id]);

        $this->assertSame(0, Notification::where('type', 'referral_assigned')->count());
    }

    public function test_at_risk_bulk_reassignment_notifies_the_new_counselor(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $from = $this->counselor();
        $to = $this->counselor();
        $student = Student::factory()->create();
        $assessment = RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 80, 'risk_level' => 'high', 'assessed_at' => now()]);
        $open = Referral::factory()->create(['student_id' => $student->id, 'counselor_id' => $from->id, 'status' => 'pending']);

        $this->actingAs($superAdmin)->post(route('admin.risk.bulkAction'), [
            'assessment_ids' => [$assessment->id], 'action' => 'assign_counselor', 'assign_counselor_id' => $to->id,
        ]);

        $this->assertSame($to->id, $open->fresh()->counselor_id);
        $this->assertSame(1, Notification::where('user_id', $to->id)->where('type', 'referral_assigned')->count());
    }

    // ── Who can work a case ─────────────────────────────────────────────

    public function test_a_counselor_cannot_change_a_colleagues_referral(): void
    {
        $owner = $this->counselor(['name' => 'Sir Cruz']);
        $other = $this->counselor();
        $referral = Referral::factory()->create(['counselor_id' => $owner->id, 'status' => 'in_progress', 'counselor_notes' => 'Owner notes']);

        $this->actingAs($other)->patch(route('counselor.referrals.updateStatus', $referral), [
            'status' => 'resolved', 'counselor_id' => $other->id, 'counselor_notes' => 'Overwritten',
        ])->assertSessionHas('error', 'This case is assigned to Sir Cruz — only they can update its status, notes or assignment.');

        $fresh = $referral->fresh();
        $this->assertSame(['in_progress', $owner->id, 'Owner notes'], [$fresh->status, $fresh->counselor_id, $fresh->counselor_notes]);
    }

    public function test_the_owner_and_anyone_on_an_unassigned_case_can_change_it(): void
    {
        $owner = $this->counselor();
        $mine = Referral::factory()->create(['counselor_id' => $owner->id, 'status' => 'pending']);
        $open = Referral::factory()->create(['counselor_id' => null, 'status' => 'pending']);

        $this->actingAs($owner)->patch(route('counselor.referrals.updateStatus', $mine), ['status' => 'in_progress', 'counselor_id' => $owner->id])
            ->assertSessionHas('success');
        $this->actingAs($this->counselor())->patch(route('counselor.referrals.updateStatus', $open), ['status' => 'in_progress'])
            ->assertSessionHas('success');

        $this->assertSame('in_progress', $mine->fresh()->status);
        $this->assertSame('in_progress', $open->fresh()->status);
    }

    public function test_a_deactivated_counselors_case_is_not_stranded(): void
    {
        $gone = $this->counselor(['deactivated_at' => now()]);
        $other = $this->counselor();
        $referral = Referral::factory()->create(['counselor_id' => $gone->id, 'status' => 'pending']);

        $this->actingAs($other)->patch(route('counselor.referrals.updateStatus', $referral), ['status' => 'in_progress', 'counselor_id' => $other->id])
            ->assertSessionHas('success');

        $this->assertSame($other->id, $referral->fresh()->counselor_id);
    }

    public function test_the_referral_page_locks_editing_for_a_colleague_but_still_shows_the_case(): void
    {
        $owner = $this->counselor(['name' => 'Sir Cruz']);
        $referral = Referral::factory()->create(['counselor_id' => $owner->id, 'status' => 'pending']);

        $this->actingAs($this->counselor())->get(route('counselor.referrals.show', $referral))->assertOk()
            ->assertSee('data-referral-locked', false)
            ->assertSee('data-intervention-locked', false)
            ->assertSee('Assigned to Sir Cruz')
            ->assertDontSee(route('counselor.referrals.updateStatus', $referral), false);

        $this->actingAs($owner)->get(route('counselor.referrals.show', $referral))
            ->assertDontSee('data-referral-locked', false)
            ->assertSee(route('counselor.referrals.updateStatus', $referral), false);
    }

    public function test_a_counselor_cannot_log_a_session_on_a_colleagues_case(): void
    {
        $owner = $this->counselor(['name' => 'Sir Cruz']);
        $other = $this->counselor();
        $theirs = Referral::factory()->create(['counselor_id' => $owner->id, 'status' => 'pending']);
        $open = Referral::factory()->create(['counselor_id' => null, 'status' => 'pending']);

        // Not offered in the form…
        $this->actingAs($other)->get(route('counselor.interventions.create'))
            ->assertDontSee('value="' . $theirs->id . '"', false)
            ->assertSee('value="' . $open->id . '"', false);

        // …and refused if posted anyway, without claiming the case.
        $this->actingAs($other)->post(route('counselor.interventions.store'), [
            'referral_id' => $theirs->id, 'intervention_type' => 'Individual Counseling',
            'intervention_date' => now()->toDateString(), 'description' => 'Session',
        ])->assertSessionHasErrors(['referral_id' => 'This case is assigned to Sir Cruz — only they can log sessions on it.']);

        $this->assertSame(0, $theirs->interventions()->count());
        $this->assertSame(['pending', $owner->id], [$theirs->fresh()->status, $theirs->fresh()->counselor_id]);
    }

    // ── Forms ───────────────────────────────────────────────────────────

    public function test_referral_form_errors_read_plainly(): void
    {
        $this->actingAs($this->counselor())->post(route('counselor.referrals.store'), [])
            ->assertSessionHasErrors([
                'student_id' => 'Choose a student.',
                'referral_type' => 'Choose a referral type.',
                'reason' => 'Describe the reason for the referral.',
            ]);
    }

    public function test_intervention_form_errors_read_plainly(): void
    {
        $this->actingAs($this->counselor())->post(route('counselor.interventions.store'), [])
            ->assertSessionHasErrors([
                'referral_id' => 'Choose the student / referral this session is for.',
                'intervention_type' => 'Choose an intervention type.',
                'description' => 'Write a few notes about the session.',
            ]);
    }

    public function test_the_referral_form_has_a_searchable_student_picker_and_bordered_fields(): void
    {
        $student = Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'student_id_number' => '2026-0042', 'status' => 'active']);
        Student::factory()->create(['first_name' => 'Gone', 'status' => 'graduated']);

        $html = $this->actingAs($this->counselor())->get(route('counselor.referrals.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-student-picker', $html);
        $this->assertStringContainsString('<input type="hidden" name="student_id"', $html);
        $this->assertStringContainsString('Cruz, Ana', $html);
        $this->assertStringContainsString('2026-0042', $html);
        $this->assertStringNotContainsString('Gone', $html);
        // Real borders (a colour without a width drew none).
        $this->assertStringContainsString('rounded-xl border bg-white', $html);
        $this->assertStringNotContainsString('rounded-lg border-gray-300', $html);
    }

    public function test_a_failed_referral_keeps_the_chosen_student(): void
    {
        $student = Student::factory()->create(['status' => 'active']);
        $counselor = $this->counselor();

        $this->actingAs($counselor)->from(route('counselor.referrals.create'))
            ->post(route('counselor.referrals.store'), ['student_id' => $student->id]);

        $this->actingAs($counselor)->get(route('counselor.referrals.create'))
            ->assertSee("selected: '{$student->id}'", false);
    }
}
