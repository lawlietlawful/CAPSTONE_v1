<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A counselor changing a behavioral report's status (single or bulk) used to
 * be a silent DB update — the reporting teacher had no way to know their
 * report was looked at, unlike a referral's own status-change notification.
 * Covers the fix that routes both paths through
 * BehavioralReportService::updateStatus(), which now also notifies the filer.
 */
class BehavioralReportStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_updating_status_notifies_the_reporting_teacher(): void
    {
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);

        $this->actingAs($counselor)->patch(route('counselor.behavioral-reports.updateStatus', $report->id), [
            'status'          => 'reviewed',
            'counselor_notes' => 'Talked to the student.',
        ])->assertRedirect();

        $report->refresh();
        $this->assertSame('reviewed', $report->status);
        $this->assertSame('Talked to the student.', $report->counselor_notes);

        $this->assertDatabaseHas('notifications', [
            'user_id'        => $report->reported_by,
            'type'           => 'report_status',
            'reference_type' => 'behavioral_report',
            'reference_id'   => $report->id,
        ]);
    }

    public function test_bulk_marking_resolved_notifies_each_reporting_teacher(): void
    {
        $counselor = $this->counselor();
        $reportA = BehavioralReport::factory()->create(['status' => 'pending']);
        $reportB = BehavioralReport::factory()->create(['status' => 'reviewed']);

        $this->actingAs($counselor)->post(route('counselor.behavioral-reports.bulkAction'), [
            'ids'    => [$reportA->id, $reportB->id],
            'action' => 'mark_resolved',
        ])->assertRedirect();

        $this->assertSame('resolved', $reportA->fresh()->status);
        $this->assertSame('resolved', $reportB->fresh()->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reportA->reported_by, 'reference_type' => 'behavioral_report', 'reference_id' => $reportA->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reportB->reported_by, 'reference_type' => 'behavioral_report', 'reference_id' => $reportB->id,
        ]);
    }

    public function test_no_notification_when_filer_is_not_a_teacher(): void
    {
        $counselor = $this->counselor();
        $otherCounselor = User::factory()->create(['role' => 'admin']);
        $report = BehavioralReport::factory()->create(['status' => 'pending', 'reported_by' => $otherCounselor->id]);

        $this->actingAs($counselor)->patch(route('counselor.behavioral-reports.updateStatus', $report->id), [
            'status' => 'reviewed',
        ])->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'reference_type' => 'behavioral_report', 'reference_id' => $report->id,
        ]);
    }

    public function test_clicking_the_notification_deep_links_to_the_report(): void
    {
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create(['status' => 'pending']);
        $teacher = $report->reportedBy;

        $this->actingAs($counselor)->patch(route('counselor.behavioral-reports.updateStatus', $report->id), [
            'status' => 'reviewed',
        ]);

        $notification = Notification::where('reference_type', 'behavioral_report')->where('reference_id', $report->id)->firstOrFail();

        $this->actingAs($teacher)->get(route('teacher.notifications.show', $notification->id))
            ->assertRedirect(route('teacher.behavioral-reports.show', $report->id));

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_index_view_report_link_is_a_single_well_formed_anchor(): void
    {
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create();

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.index'));

        $response->assertSee(
            '<a href="' . route('counselor.behavioral-reports.show', $report->id) . '" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="View Report">',
            false
        );

        // The "View Report" anchor used to never close before the wrapping
        // </div>, which browsers "fix" via mismatched-tag recovery in ways
        // that can bleed the clickable/hover area into unrelated markup.
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*behavioral-reports\/' . $report->id . '"[^>]*title="View Report">\s*<i class="ti ti-eye"><\/i>\s*<\/a>\s*<\/div>/',
            $response->getContent()
        );
    }

    public function test_csv_export_escapes_embedded_quotes_in_description(): void
    {
        $counselor = $this->counselor();
        BehavioralReport::factory()->create([
            'description' => 'The student said "I did not do it" during questioning.',
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = array_map('str_getcsv', array_filter(explode("\n", $response->streamedContent())));
        $this->assertCount(2, $rows);
        $this->assertSame('The student said "I did not do it" during questioning.', $rows[1][8]);
    }
}
