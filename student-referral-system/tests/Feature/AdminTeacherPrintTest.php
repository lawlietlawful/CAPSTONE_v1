<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin\TeacherController@print — the "Print / Export PDF" report. Every row
 * used to render with a blank Student Name and blank Report Type: it read
 * $report->student->name (Student has no such field — it's
 * first_name/last_name) and $report->report_type (BehavioralReport has no
 * such field — it's incident_type). The stats row was also a 4-column grid
 * with only 2 boxes, and referrals were never included at all even though
 * Engagement weighs them equally against reports.
 */
class AdminTeacherPrintTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->counselor()->create();
    }

    private function print(User $teacher): string
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.teachers.print', $teacher->id))
            ->assertOk()
            ->getContent();
    }

    public function test_a_reports_row_shows_the_students_name_and_incident_type_not_blank_cells(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = Student::factory()->create(['first_name' => 'Angelo', 'last_name' => 'Fernandez']);
        BehavioralReport::factory()->create([
            'reported_by' => $teacher->id, 'student_id' => $student->id,
            'incident_type' => 'Academic Failure',
        ]);

        $html = $this->print($teacher);

        $this->assertStringContainsString('Fernandez, Angelo', $html);
        $this->assertStringContainsString('Academic Failure', $html);
    }

    public function test_the_report_now_includes_a_referrals_section(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = Student::factory()->create(['first_name' => 'Angelo', 'last_name' => 'Fernandez']);
        Referral::factory()->create([
            'referred_by' => $teacher->id, 'student_id' => $student->id,
            'referral_type' => 'Misconduct', 'priority' => 'high',
        ]);

        $html = $this->print($teacher);

        $this->assertStringContainsString('Recent Referrals Filed', $html);
        $this->assertStringContainsString('Fernandez, Angelo', $html);
        $this->assertStringContainsString('Referrals Filed', $html, 'the stat box, not just the section title');
    }

    public function test_the_stats_grid_has_three_columns_matching_its_three_boxes(): void
    {
        $teacher = User::factory()->teacher()->create();

        $html = $this->print($teacher);

        $this->assertStringContainsString('grid-template-columns: repeat(3, 1fr)', $html);
        // class="stat-box", not the CSS rule itself (which also contains the substring).
        $this->assertSame(3, substr_count($html, 'class="stat-box"'));
    }

    public function test_an_in_progress_referral_reads_as_two_words_not_with_an_underscore(): void
    {
        $teacher = User::factory()->teacher()->create();
        Referral::factory()->create(['referred_by' => $teacher->id, 'status' => 'in_progress']);

        $html = $this->print($teacher);

        $this->assertStringContainsString('in progress', $html);
        $this->assertStringNotContainsString('in_progress', $html);
    }

    public function test_a_teacher_with_neither_reports_nor_referrals_shows_both_empty_states(): void
    {
        $teacher = User::factory()->teacher()->create();

        $html = $this->print($teacher);

        $this->assertStringContainsString('No behavioral reports found.', $html);
        $this->assertStringContainsString('No referrals found.', $html);
    }

    public function test_only_this_teachers_own_activity_appears(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $mine = Student::factory()->create(['first_name' => 'Alice', 'last_name' => 'Ownstudent']);
        $theirs = Student::factory()->create(['first_name' => 'Bob', 'last_name' => 'Otherstudent']);
        BehavioralReport::factory()->create(['reported_by' => $teacher->id, 'student_id' => $mine->id]);
        BehavioralReport::factory()->create(['reported_by' => $other->id, 'student_id' => $theirs->id]);

        $html = $this->print($teacher);

        $this->assertStringContainsString('Ownstudent, Alice', $html);
        $this->assertStringNotContainsString('Otherstudent, Bob', $html);
    }
}
