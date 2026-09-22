<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the remaining index/show page findings from the Behavioral Reports
 * audit: pagination size, clickable summary-card filters, the student-name
 * link, and the "graded but no assessment on file" fallback.
 */
class BehavioralReportIndexAndShowTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_paginates_at_ten_per_page(): void
    {
        $counselor = $this->counselor();
        BehavioralReport::factory()->count(12)->create();

        $page = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.index'));

        $reports = $page->viewData('reports');
        $this->assertSame(10, $reports->count());
        $this->assertSame(12, $reports->total());
    }

    public function test_pending_summary_card_links_to_the_pending_filter(): void
    {
        $counselor = $this->counselor();
        BehavioralReport::factory()->create(['status' => 'pending']);
        BehavioralReport::factory()->create(['status' => 'resolved']);

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.index'));

        $response->assertSee(route('counselor.behavioral-reports.index', ['status' => 'pending']), false);

        $filtered = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.index', ['status' => 'pending']));
        $this->assertSame(1, $filtered->viewData('reports')->total());
    }

    public function test_student_name_on_show_page_links_to_the_student_profile(): void
    {
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create();

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.show', $report->id));

        $response->assertSee(route('admin.students.show', $report->student_id), false);
    }

    public function test_graded_report_with_no_risk_assessment_shows_a_fallback_message(): void
    {
        $counselor = $this->counselor();
        // Simulates legacy data: severity is graded (not the Unassessed
        // sentinel) but no RiskAssessment row was ever linked.
        $report = BehavioralReport::factory()->create(['severity' => 'High', 'risk_assessment_id' => null]);

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.show', $report->id));

        $response->assertSee('No AI risk assessment is on file for this report.');
    }

    public function test_report_with_a_risk_assessment_still_shows_the_assessment_panel(): void
    {
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create(['severity' => 'High', 'risk_assessment_id' => null]);
        $assessment = RiskAssessment::create([
            'student_id'                => $report->student_id,
            'previous_referrals_count'  => 1,
            'behavioral_reports_count'  => 2,
            'concern_type_encoded'      => 1,
            'days_since_last_referral'  => 10,
            'risk_score'                => 82,
            'risk_level'                => 'high',
            'risk_factors'              => [],
            'assessed_at'               => now(),
        ]);
        $report->update(['risk_assessment_id' => $assessment->id]);

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.show', $report->id));

        $response->assertSee('AI Risk Assessment');
        $response->assertDontSee('No AI risk assessment is on file for this report.');
    }

    public function test_other_reports_for_the_same_student_are_listed_and_linked(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create();
        $current = BehavioralReport::factory()->create(['student_id' => $student->id, 'incident_date' => now()]);
        $older = BehavioralReport::factory()->create(['student_id' => $student->id, 'incident_date' => now()->subDays(10)]);
        $unrelated = BehavioralReport::factory()->create();

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.show', $current->id));

        $response->assertSee('Other Reports for This Student');
        $response->assertSee(route('counselor.behavioral-reports.show', $older->id), false);
        $response->assertDontSee(route('counselor.behavioral-reports.show', $unrelated->id), false);
    }

    public function test_no_other_reports_section_when_student_has_no_other_reports(): void
    {
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create();

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.show', $report->id));

        $response->assertDontSee('Other Reports for This Student');
    }

    public function test_more_than_five_other_reports_shows_a_view_all_link(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create();
        $current = BehavioralReport::factory()->create(['student_id' => $student->id]);
        BehavioralReport::factory()->count(6)->create(['student_id' => $student->id]);

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.show', $current->id));

        $response->assertSee('View all 7 reports for this student');
        $response->assertSee(route('counselor.behavioral-reports.index', ['search' => $student->student_id_number]), false);
    }
}
