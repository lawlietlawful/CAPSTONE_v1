<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BehavioralReportService::updateStatus() used to notify the reporting
 * teacher on every call, so saving notes on an unchanged report (or
 * bulk-marking an already-reviewed one) sent a false "status changed" alert.
 */
class BehavioralReportNotificationGuardTest extends TestCase
{
    use RefreshDatabase;

    private function reportBy(User $teacher, string $status): BehavioralReport
    {
        return BehavioralReport::factory()->create(['reported_by' => $teacher->id, 'status' => $status]);
    }

    public function test_saving_notes_without_changing_status_does_not_notify_the_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $report = $this->reportBy($teacher, 'reviewed');

        $this->actingAs(User::factory()->counselor()->create())
            ->patch(route('admin.behavioral-reports.updateStatus', $report->id), [
                'status' => 'reviewed', 'counselor_notes' => 'Just a note.',
            ]);

        $this->assertSame('Just a note.', $report->fresh()->counselor_notes);
        $this->assertSame(0, Notification::where('user_id', $teacher->id)->count());
    }

    public function test_bulk_marking_an_already_reviewed_report_does_not_notify(): void
    {
        $teacher = User::factory()->teacher()->create();
        $report = $this->reportBy($teacher, 'reviewed');

        $this->actingAs(User::factory()->counselor()->create())
            ->post(route('admin.behavioral-reports.bulkAction'), [
                'ids' => [$report->id], 'action' => 'mark_reviewed',
            ]);

        $this->assertSame(0, Notification::where('user_id', $teacher->id)->count());
    }

    public function test_an_actual_status_change_still_notifies_the_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $report = $this->reportBy($teacher, 'pending');

        $this->actingAs(User::factory()->counselor()->create())
            ->patch(route('admin.behavioral-reports.updateStatus', $report->id), ['status' => 'reviewed']);

        $this->assertSame(1, Notification::where('user_id', $teacher->id)->count());
    }
}
