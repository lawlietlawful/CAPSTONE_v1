<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors BehavioralReportIndexStudentLinkTest for the Admin-side page
 * (resources/views/admin/behavioral-reports/index.blade.php), which had the
 * identical plain-text-name issue as the Counselor page it was copied from.
 */
class AdminBehavioralReportIndexStudentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_student_name_links_to_that_rows_own_report(): void
    {
        $admin = $this->admin();
        $report = BehavioralReport::factory()->create();

        $page = $this->actingAs($admin)->get(route('admin.behavioral-reports.index'));

        $page->assertSee(route('admin.behavioral-reports.show', $report->id), false);
    }
}
