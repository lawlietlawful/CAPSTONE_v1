<?php

namespace Tests\Feature;

use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The student profile page's "Risk Lvl" stat used to default an unassessed
 * student to "Low" (risk_level ?? 'low'), which reads as "the AI checked
 * this student and cleared them" when really the AI never ran at all — the
 * same distinction the rest of the app is careful about (behavioral reports
 * show "Not yet assessed" for exactly this case). It also read the latest
 * assessment via riskAssessments->last() (collection load order) instead of
 * the proper latestRiskAssessment relation everywhere else already trusts.
 */
class AdminStudentShowTest extends TestCase
{
    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    use RefreshDatabase;

    public function test_a_never_assessed_student_shows_not_assessed_not_low(): void
    {
        $student = Student::factory()->create();

        $response = $this->actingAs($this->counselor())->get(route('admin.students.show', $student->id));

        $response->assertOk();
        $response->assertSee('Not Assessed');
        $response->assertDontSee('Low');
    }

    public function test_a_never_assessed_student_does_not_show_the_ai_risk_breakdown_panel(): void
    {
        $student = Student::factory()->create();

        $response = $this->actingAs($this->counselor())->get(route('admin.students.show', $student->id));

        $response->assertOk();
        $response->assertDontSee('AI Risk Analysis Breakdown');
    }

    public function test_an_assessed_student_shows_their_real_risk_level(): void
    {
        $student = Student::factory()->create();
        RiskAssessment::create([
            'student_id' => $student->id,
            'risk_score' => 88,
            'risk_level' => 'high',
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($this->counselor())->get(route('admin.students.show', $student->id));

        $response->assertOk();
        $response->assertSee('High');
        $response->assertSee('AI Risk Analysis Breakdown');
    }

    public function test_the_most_recently_assessed_risk_level_is_the_one_shown(): void
    {
        $student = Student::factory()->create();

        // An old 'high' assessment has since been superseded by a newer 'low' one.
        RiskAssessment::create([
            'student_id' => $student->id, 'risk_score' => 90, 'risk_level' => 'high',
            'assessed_at' => now()->subWeek(),
        ]);
        RiskAssessment::create([
            'student_id' => $student->id, 'risk_score' => 20, 'risk_level' => 'low',
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($this->counselor())->get(route('admin.students.show', $student->id));

        $response->assertOk();
        $response->assertViewHas('student', function ($viewStudent) {
            return $viewStudent->latestRiskAssessment->risk_level === 'low';
        });
    }
}
