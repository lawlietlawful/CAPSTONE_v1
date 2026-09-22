<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The student name in the index table used to be plain text. Per the same
 * correction already made on the Intervention index, clicking a row's name
 * should go to that row's own report — not the student's profile — since a
 * student can have several reports on file.
 */
class BehavioralReportIndexStudentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_student_name_links_to_that_rows_own_report(): void
    {
        $counselor = $this->counselor();
        $report = BehavioralReport::factory()->create();

        $page = $this->actingAs($counselor)->get(route('counselor.behavioral-reports.index'));

        $page->assertSee(route('counselor.behavioral-reports.show', $report->id), false);
        $page->assertDontSee(route('admin.students.show', $report->student_id), false);
    }
}
