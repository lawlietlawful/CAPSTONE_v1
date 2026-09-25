<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The counselor dashboard's High-Risk Watchlist shows only "my" students
 * (assigned to this counselor, or unclaimed), but its "View All" link used
 * to go to the plain /admin/risk page — the whole school, unfiltered. This
 * locks down the new ?scope=mine filter that makes "View All" actually
 * show more of what the widget itself was already showing.
 */
class AdminRiskScopeTest extends TestCase
{
    use RefreshDatabase;

    private function highRiskStudent(): Student
    {
        $student = Student::factory()->create();
        RiskAssessment::create([
            'student_id' => $student->id,
            'risk_score' => 90,
            'risk_level' => 'high',
            'assessed_at' => now(),
        ]);

        return $student;
    }

    public function test_without_scope_a_counselor_sees_every_high_risk_student(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();

        $mine = $this->highRiskStudent();
        Referral::factory()->create(['student_id' => $mine->id, 'counselor_id' => $counselor->id]);

        $notMine = $this->highRiskStudent();
        Referral::factory()->create(['student_id' => $notMine->id, 'counselor_id' => $otherCounselor->id]);

        $response = $this->actingAs($counselor)->get(route('admin.risk.index'));

        $response->assertOk();
        $response->assertSee($mine->first_name);
        $response->assertSee($notMine->first_name);
        $response->assertViewHas('totalAssessed', 2);
    }

    public function test_scope_mine_narrows_to_assigned_or_unclaimed_students_only(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();

        $assignedToMe = $this->highRiskStudent();
        Referral::factory()->create(['student_id' => $assignedToMe->id, 'counselor_id' => $counselor->id]);

        $unclaimed = $this->highRiskStudent();
        Referral::factory()->create(['student_id' => $unclaimed->id, 'counselor_id' => null]);

        $assignedToSomeoneElse = $this->highRiskStudent();
        Referral::factory()->create(['student_id' => $assignedToSomeoneElse->id, 'counselor_id' => $otherCounselor->id]);

        $response = $this->actingAs($counselor)->get(route('admin.risk.index', ['scope' => 'mine']));

        $response->assertOk();
        $response->assertSee($assignedToMe->first_name);
        $response->assertSee($unclaimed->first_name);
        $response->assertDontSee($assignedToSomeoneElse->first_name);
        $response->assertViewHas('totalAssessed', 2);
    }

    public function test_scope_mine_also_narrows_the_summary_counts(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();

        $mine = $this->highRiskStudent();
        Referral::factory()->create(['student_id' => $mine->id, 'counselor_id' => $counselor->id]);

        // Two more high-risk students belonging to another counselor — must
        // not inflate "my" high-risk count.
        foreach (range(1, 2) as $i) {
            $student = $this->highRiskStudent();
            Referral::factory()->create(['student_id' => $student->id, 'counselor_id' => $otherCounselor->id]);
        }

        $response = $this->actingAs($counselor)->get(route('admin.risk.index', ['scope' => 'mine']));

        $response->assertViewHas('highRiskCount', 1);
    }

    public function test_scope_mine_combines_with_the_existing_risk_level_filter(): void
    {
        $counselor = User::factory()->counselor()->create();

        $highRisk = $this->highRiskStudent();
        $highRisk->update(['first_name' => 'Zzhighone']); // fixed names: a random fake name can appear elsewhere on the page
        Referral::factory()->create(['student_id' => $highRisk->id, 'counselor_id' => $counselor->id]);

        $moderateStudent = Student::factory()->create(['first_name' => 'Zzmodone']);
        RiskAssessment::create(['student_id' => $moderateStudent->id, 'risk_score' => 50, 'risk_level' => 'moderate', 'assessed_at' => now()]);
        Referral::factory()->create(['student_id' => $moderateStudent->id, 'counselor_id' => $counselor->id]);

        $response = $this->actingAs($counselor)->get(route('admin.risk.index', ['scope' => 'mine', 'risk_level' => 'high']));

        $response->assertOk();
        $response->assertSee($highRisk->first_name);
        $response->assertDontSee($moderateStudent->first_name);
    }

    public function test_scope_mine_is_a_no_op_for_a_super_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $counselor = User::factory()->counselor()->create();

        $assignedToCounselor = $this->highRiskStudent();
        Referral::factory()->create(['student_id' => $assignedToCounselor->id, 'counselor_id' => $counselor->id]);

        // A super_admin has no personal referral scope, so ?scope=mine
        // must not accidentally hide everyone else's students.
        $response = $this->actingAs($superAdmin)->get(route('admin.risk.index', ['scope' => 'mine']));

        $response->assertOk();
        $response->assertSee($assignedToCounselor->first_name);
    }

    public function test_the_counselor_dashboard_watchlist_links_to_the_scoped_view(): void
    {
        $counselor = User::factory()->counselor()->create();

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.risk.index', ['scope' => 'mine']), false);
    }
}
