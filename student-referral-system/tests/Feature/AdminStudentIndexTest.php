<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Students index page's delete-safety warning: deleting a student
 * cascades to permanently erase every referral, behavioral report, and risk
 * assessment on file for them (see the FKs on those tables), but the
 * confirmation dialog used to say only a generic "are you sure?" with no
 * mention of that. The counts backing the real warning message come from
 * StudentController::index()'s withCount() call.
 */
class AdminStudentIndexTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    public function test_index_attaches_related_record_counts_to_each_student(): void
    {
        $student = Student::factory()->create();
        Referral::factory()->count(2)->create(['student_id' => $student->id]);
        BehavioralReport::factory()->create(['student_id' => $student->id]);
        RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 50, 'risk_level' => 'moderate']);

        $response = $this->actingAs($this->counselor())->get(route('admin.students.index'));

        $response->assertOk();
        $response->assertViewHas('students', function ($students) use ($student) {
            $row = $students->firstWhere('id', $student->id);
            return $row->referrals_count === 2
                && $row->behavioral_reports_count === 1
                && $row->risk_assessments_count === 1;
        });
    }

    public function test_index_shows_zero_counts_for_a_student_with_no_history(): void
    {
        $student = Student::factory()->create();

        $response = $this->actingAs($this->counselor())->get(route('admin.students.index'));

        $response->assertViewHas('students', function ($students) use ($student) {
            $row = $students->firstWhere('id', $student->id);
            return $row->referrals_count === 0
                && $row->behavioral_reports_count === 0
                && $row->risk_assessments_count === 0;
        });
    }

    public function test_delete_warning_mentions_referral_count_when_present(): void
    {
        $student = Student::factory()->create(['first_name' => 'Juanita']);
        Referral::factory()->create(['student_id' => $student->id]);

        $response = $this->actingAs($this->counselor())->get(route('admin.students.index'));

        $response->assertSee('Deleting Juanita will also permanently erase 1 referral', false);
    }

    public function test_delete_warning_is_generic_for_a_student_with_no_history(): void
    {
        $student = Student::factory()->create(['first_name' => 'Pedro']);

        $response = $this->actingAs($this->counselor())->get(route('admin.students.index'));

        $response->assertSee('Are you sure you want to delete Pedro?', false);
        $response->assertDontSee('will also permanently erase', false);
    }

    // ── At-Risk Students: count and filter, both "latest assessment only" ──
    //
    // These used to check "has ANY high/moderate assessment ever", so a
    // student who improved to 'low' after being high-risk months ago would
    // still count and still show up under the filter forever.

    public function test_at_risk_count_reflects_only_the_latest_assessment(): void
    {
        // Improved: old 'high' superseded by a newer 'low' — must not count.
        $improved = Student::factory()->create();
        RiskAssessment::create(['student_id' => $improved->id, 'risk_score' => 90, 'risk_level' => 'high', 'assessed_at' => now()->subWeek()]);
        RiskAssessment::create(['student_id' => $improved->id, 'risk_score' => 10, 'risk_level' => 'low', 'assessed_at' => now()]);

        // Currently at risk — must count.
        $atRisk = Student::factory()->create();
        RiskAssessment::create(['student_id' => $atRisk->id, 'risk_score' => 80, 'risk_level' => 'high', 'assessed_at' => now()]);

        $response = $this->actingAs($this->counselor())->get(route('admin.students.index'));

        $response->assertViewHas('atRiskStudents', 1);
    }

    public function test_risk_level_filter_shows_only_currently_at_risk_students(): void
    {
        $improved = Student::factory()->create();
        RiskAssessment::create(['student_id' => $improved->id, 'risk_score' => 90, 'risk_level' => 'high', 'assessed_at' => now()->subWeek()]);
        RiskAssessment::create(['student_id' => $improved->id, 'risk_score' => 10, 'risk_level' => 'low', 'assessed_at' => now()]);

        $atRisk = Student::factory()->create();
        RiskAssessment::create(['student_id' => $atRisk->id, 'risk_score' => 80, 'risk_level' => 'high', 'assessed_at' => now()]);

        $response = $this->actingAs($this->counselor())->get(route('admin.students.index', ['risk_level' => 'at_risk']));

        $response->assertViewHas('students', function ($students) use ($atRisk, $improved) {
            $ids = $students->pluck('id');
            return $ids->contains($atRisk->id) && ! $ids->contains($improved->id);
        });
    }

    public function test_risk_badge_shows_in_the_table_for_an_at_risk_student(): void
    {
        $student = Student::factory()->create(['first_name' => 'Marisol']);
        RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 85, 'risk_level' => 'high', 'assessed_at' => now()]);

        $response = $this->actingAs($this->counselor())->get(route('admin.students.index'));

        $response->assertSee('Marisol');
        $response->assertSee('High');
    }

    // ── With Referrals filter ────────────────────────────────────────────

    public function test_has_referrals_filter_shows_only_students_with_a_referral(): void
    {
        $withReferral = Student::factory()->create();
        Referral::factory()->create(['student_id' => $withReferral->id]);
        $withoutReferral = Student::factory()->create();

        $response = $this->actingAs($this->counselor())->get(route('admin.students.index', ['has_referrals' => 1]));

        $response->assertViewHas('students', function ($students) use ($withReferral, $withoutReferral) {
            $ids = $students->pluck('id');
            return $ids->contains($withReferral->id) && ! $ids->contains($withoutReferral->id);
        });
    }
}
