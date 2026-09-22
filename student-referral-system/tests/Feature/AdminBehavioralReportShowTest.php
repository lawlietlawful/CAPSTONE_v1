<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors the Counselor-side coverage (BehavioralReportStatusUpdateTest,
 * BehavioralReportIndexAndShowTest) for the Admin controller/views, which had
 * fallen behind: no notification on status change, no "Other Reports for
 * This Student" section, and no fallback when a graded report has no linked
 * RiskAssessment. All three were ported over to bring the two pages back to
 * parity.
 */
class AdminBehavioralReportShowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_updating_status_notifies_the_reporting_teacher(): void
    {
        $admin = $this->admin();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);

        $this->actingAs($admin)->patch(route('admin.behavioral-reports.updateStatus', $report->id), [
            'status'          => 'reviewed',
            'counselor_notes' => 'Reviewed by admin.',
        ])->assertRedirect();

        $report->refresh();
        $this->assertSame('reviewed', $report->status);
        $this->assertDatabaseHas('notifications', [
            'user_id'        => $report->reported_by,
            'type'           => 'report_status',
            'reference_type' => 'behavioral_report',
            'reference_id'   => $report->id,
        ]);
    }

    public function test_bulk_marking_resolved_notifies_each_reporting_teacher(): void
    {
        $admin = $this->admin();
        $reportA = BehavioralReport::factory()->create(['status' => 'pending']);
        $reportB = BehavioralReport::factory()->create(['status' => 'reviewed']);

        $this->actingAs($admin)->post(route('admin.behavioral-reports.bulkAction'), [
            'ids'    => [$reportA->id, $reportB->id],
            'action' => 'mark_resolved',
        ])->assertRedirect();

        $this->assertSame('resolved', $reportA->fresh()->status);
        $this->assertSame('resolved', $reportB->fresh()->status);
        $this->assertDatabaseHas('notifications', ['reference_type' => 'behavioral_report', 'reference_id' => $reportA->id]);
        $this->assertDatabaseHas('notifications', ['reference_type' => 'behavioral_report', 'reference_id' => $reportB->id]);
    }

    public function test_other_reports_for_the_same_student_are_listed_and_linked(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create();
        $current = BehavioralReport::factory()->create(['student_id' => $student->id, 'incident_date' => now()]);
        $older = BehavioralReport::factory()->create(['student_id' => $student->id, 'incident_date' => now()->subDays(10)]);
        $unrelated = BehavioralReport::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.behavioral-reports.show', $current->id));

        $response->assertSee('Other Reports for This Student');
        $response->assertSee(route('admin.behavioral-reports.show', $older->id), false);
        $response->assertDontSee(route('admin.behavioral-reports.show', $unrelated->id), false);
    }

    public function test_graded_report_with_no_risk_assessment_shows_a_fallback_message(): void
    {
        $admin = $this->admin();
        $report = BehavioralReport::factory()->create(['severity' => 'High', 'risk_assessment_id' => null]);

        $response = $this->actingAs($admin)->get(route('admin.behavioral-reports.show', $report->id));

        $response->assertSee('No AI risk assessment is on file for this report.');
    }

    public function test_clicking_the_notification_deep_links_to_the_teacher_report_page(): void
    {
        $admin = $this->admin();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);
        $teacher = $report->reportedBy;

        $this->actingAs($admin)->patch(route('admin.behavioral-reports.updateStatus', $report->id), [
            'status' => 'reviewed',
        ]);

        $notification = Notification::where('reference_type', 'behavioral_report')->where('reference_id', $report->id)->firstOrFail();

        $this->actingAs($teacher)->get(route('teacher.notifications.show', $notification->id))
            ->assertRedirect(route('teacher.behavioral-reports.show', $report->id));
    }
}
