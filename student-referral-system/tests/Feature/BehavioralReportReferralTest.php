<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * A behavioral report that did not auto-escalate used to be a dead end: no
 * referral, so no assessment follow-up and nothing to log interventions
 * against. A counselor can now open a referral from the report itself.
 */
class BehavioralReportReferralTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function report(array $extra = []): BehavioralReport
    {
        return BehavioralReport::factory()->create(array_merge(['status' => 'pending'], $extra));
    }

    private function refer(User $by, BehavioralReport $report, array $data = [], string $area = 'counselor')
    {
        return $this->actingAs($by)->post(route($area . '.behavioral-reports.refer', $report->id), $data);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMlEngine('moderate', 55, 'values_formation');
    }

    public function test_a_counselor_can_open_a_referral_from_a_report(): void
    {
        $c = $this->counselor();
        $report = $this->report(['incident_type' => 'Disciplinary Incident', 'description' => 'Argued with the teacher in class.']);

        $this->refer($c, $report)->assertSessionHas('success');

        $referral = Referral::where('behavioral_report_id', $report->id)->sole();
        $this->assertSame($report->student_id, $referral->student_id);
        $this->assertSame('Misconduct', $referral->referral_type);
        $this->assertSame('behavioral', $referral->concern_type);
        $this->assertSame($c->id, $referral->counselor_id, 'a counselor files it to themselves by default');
        $this->assertSame('pending', $referral->status);
        $this->assertStringStartsWith("[From Behavioral Report #{$report->id}]", $referral->reason);
        $this->assertStringContainsString('Argued with the teacher in class.', $referral->reason);
        $this->assertNotNull($referral->risk_assessment_id, 'it is risk-assessed like any other referral');
    }

    public function test_an_unmapped_incident_type_is_filed_as_other_with_the_incident_type_named(): void
    {
        $report = $this->report(['incident_type' => 'Bullying']);

        $this->refer($this->counselor(), $report);

        $referral = Referral::where('behavioral_report_id', $report->id)->sole();
        $this->assertSame('Other', $referral->referral_type);
        $this->assertSame('Bullying', $referral->referral_type_other);
    }

    public function test_the_report_being_referred_is_not_counted_as_the_students_history(): void
    {
        $report = $this->report();

        $this->refer($this->counselor(), $report);

        $referral = Referral::where('behavioral_report_id', $report->id)->sole();
        $this->assertSame(0, $referral->riskAssessment->behavioral_reports_count, 'this report IS the current incident');
    }

    public function test_earlier_reports_still_count_as_history(): void
    {
        $student = Student::factory()->create();
        $this->report(['student_id' => $student->id]);
        $current = $this->report(['student_id' => $student->id]);

        $this->refer($this->counselor(), $current);

        $this->assertSame(1, Referral::where('behavioral_report_id', $current->id)->sole()->riskAssessment->behavioral_reports_count);
    }

    public function test_a_pending_report_becomes_reviewed_and_the_teacher_is_told(): void
    {
        $teacher = User::factory()->teacher()->create();
        $report = $this->report(['reported_by' => $teacher->id]);

        $this->refer($this->counselor(), $report);

        $this->assertSame('reviewed', $report->fresh()->status);
        $this->assertSame(1, Notification::where('user_id', $teacher->id)->count());
    }

    public function test_an_already_reviewed_report_keeps_its_status_and_sends_no_status_notice(): void
    {
        $teacher = User::factory()->teacher()->create();
        $report = $this->report(['reported_by' => $teacher->id, 'status' => 'reviewed']);

        $this->refer($this->counselor(), $report);

        $this->assertSame('reviewed', $report->fresh()->status);
        $this->assertSame(0, Notification::where('user_id', $teacher->id)->where('type', 'report_status')->count());
    }

    public function test_the_referral_can_be_assigned_to_another_counselor(): void
    {
        $me = $this->counselor();
        $other = $this->counselor();
        $report = $this->report();

        $this->refer($me, $report, ['counselor_id' => $other->id]);

        $this->assertSame($other->id, Referral::where('behavioral_report_id', $report->id)->sole()->counselor_id);
    }

    public function test_the_assignee_must_be_a_real_counselor(): void
    {
        $teacher = User::factory()->teacher()->create();
        $report = $this->report();

        $this->refer($this->counselor(), $report, ['counselor_id' => $teacher->id])->assertSessionHasErrors('counselor_id');

        $this->assertSame(0, Referral::count());
    }

    public function test_a_super_admin_can_refer_and_leaves_it_unassigned_when_several_counselors_exist(): void
    {
        $this->counselor();
        $this->counselor();
        $super = User::factory()->create(['role' => 'super_admin']);
        $report = $this->report();

        $this->refer($super, $report, [], 'admin')->assertSessionHas('success');

        $this->assertNull(Referral::where('behavioral_report_id', $report->id)->sole()->counselor_id);
    }

    public function test_a_report_cannot_be_referred_twice(): void
    {
        $c = $this->counselor();
        $report = $this->report();

        $this->refer($c, $report)->assertSessionHas('success');
        $this->refer($c, $report)->assertSessionHas('error');

        $this->assertSame(1, Referral::where('behavioral_report_id', $report->id)->count());
        $this->assertStringContainsString('already linked to Referral', session('error'));
    }

    public function test_an_auto_escalated_report_cannot_be_referred_again(): void
    {
        $report = $this->report();
        Referral::factory()->create(['behavioral_report_id' => $report->id, 'student_id' => $report->student_id]);

        $this->refer($this->counselor(), $report)->assertSessionHas('error');

        $this->assertSame(1, Referral::where('behavioral_report_id', $report->id)->count());
    }

    public function test_a_resolved_report_is_refused_with_a_reason(): void
    {
        $report = $this->report(['status' => 'resolved']);

        $this->refer($this->counselor(), $report)->assertSessionHas('error');

        $this->assertSame(0, Referral::count());
        $this->assertStringContainsString('already resolved', session('error'));
    }

    public function test_teachers_cannot_use_the_refer_action(): void
    {
        $report = $this->report();

        $this->refer(User::factory()->teacher()->create(), $report)->assertForbidden();

        $this->assertSame(0, Referral::count());
    }

    public function test_the_report_page_offers_the_action_only_while_there_is_no_referral(): void
    {
        $c = $this->counselor();
        $report = $this->report();

        $before = $this->actingAs($c)->get(route('counselor.behavioral-reports.show', $report->id))->getContent();
        $this->assertStringContainsString('data-report-refer-card', $before);
        $this->assertStringContainsString('Create referral from this report', $before);

        $this->refer($c, $report);

        $after = $this->actingAs($c)->get(route('counselor.behavioral-reports.show', $report->id))->getContent();
        $this->assertStringNotContainsString('data-report-refer-card', $after);
        $this->assertStringContainsString('A referral was opened from this report:', $after);
    }

    public function test_the_admin_report_page_also_offers_the_action(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $report = $this->report();

        $html = $this->actingAs($super)->get(route('admin.behavioral-reports.show', $report->id))->getContent();

        $this->assertStringContainsString('data-report-refer-card', $html);
        $this->assertStringContainsString(route('admin.behavioral-reports.refer', $report->id), $html);
    }

    public function test_a_resolved_report_shows_no_form(): void
    {
        $report = $this->report(['status' => 'resolved']);

        $html = $this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.show', $report->id))->getContent();

        $this->assertStringContainsString('Reopen it if it needs a referral', $html);
        $this->assertStringNotContainsString('Create referral from this report', $html);
    }

    public function test_an_auto_escalated_report_still_says_auto_escalated(): void
    {
        $report = $this->report();
        Referral::factory()->create([
            'behavioral_report_id' => $report->id, 'student_id' => $report->student_id,
            'reason' => "[AUTO-ESCALATED from Behavioral Report #{$report->id}] Threat.",
        ]);

        $html = $this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.show', $report->id))->getContent();

        $this->assertStringContainsString('This report was auto-escalated to', $html);
    }

    public function test_an_intervention_can_now_be_logged_for_that_students_report_case(): void
    {
        $c = $this->counselor();
        $report = $this->report();
        $this->refer($c, $report);
        $referral = Referral::where('behavioral_report_id', $report->id)->sole();

        $form = $this->actingAs($c)->get(route('counselor.interventions.create', ['referral_id' => $referral->id]))->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . $referral->id . '"\s+selected/', $form);

        $student = $this->actingAs($c)->get(route('admin.students.show', $report->student_id))->getContent();
        $this->assertStringContainsString(e(route('counselor.interventions.create', ['referral_id' => $referral->id])), $student);
    }

    public function test_resolving_the_referral_resolves_the_report(): void
    {
        $c = $this->counselor();
        $report = $this->report();
        $this->refer($c, $report);
        $referral = Referral::where('behavioral_report_id', $report->id)->sole();

        $this->actingAs($c)->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'resolved']);

        $this->assertSame('resolved', $report->fresh()->status, 'the report follows its referral');
    }

    // ── "Reports to review" tile ─────────────────────────────────────────

    public function test_the_reports_tile_counts_pending_reports_and_matches_the_list_it_opens(): void
    {
        $c = $this->counselor();
        $this->report();
        $this->report();
        $this->report(['status' => 'reviewed']);
        $this->report(['status' => 'resolved']);

        $tile = collect($this->actingAs($c)->get(route('counselor.dashboard'))->viewData('attentionTiles'))->firstWhere('key', 'reports');
        $listed = $this->actingAs($c)->get($tile['url'])->viewData('reports')->total();

        $this->assertSame(2, $tile['count']);
        $this->assertSame($tile['count'], $listed);
    }

    public function test_the_reports_tile_drops_when_a_report_is_referred(): void
    {
        $c = $this->counselor();
        $report = $this->report();
        $count = fn () => collect($this->actingAs($c)->get(route('counselor.dashboard'))->viewData('attentionTiles'))->firstWhere('key', 'reports')['count'];

        $this->assertSame(1, $count());
        $this->refer($c, $report);
        $this->assertSame(0, $count());
    }

    public function test_the_admin_reports_tile_links_to_the_admin_list(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->report();

        $tile = collect($this->actingAs($super)->get(route('admin.dashboard'))->viewData('attentionTiles'))->firstWhere('key', 'reports');

        $this->assertSame(1, $tile['count']);
        $this->assertSame($tile['count'], $this->actingAs($super)->get($tile['url'])->viewData('reports')->total());
    }
}
