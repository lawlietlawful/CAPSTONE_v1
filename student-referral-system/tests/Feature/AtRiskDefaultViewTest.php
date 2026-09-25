<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "At-Risk Students" means High + Moderate. The list used to include Low-risk
 * students by default (5 of 11 rows on the dev data) while the Students page
 * said 6 students were at risk. Low is one click away, and the page has one name.
 */
class AtRiskDefaultViewTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function assess(string $level, float $score, string $name = 'Person', $at = null): Student
    {
        $s = Student::factory()->create(['first_name' => $name]);
        RiskAssessment::create(['student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level, 'assessed_at' => $at ?? now()]);

        return $s;
    }

    private function names($page): array
    {
        return $page->viewData('assessments')->map(fn ($a) => $a->student->first_name)->sort()->values()->all();
    }

    private function threeStudents(): void
    {
        $this->assess('high', 90, 'Highone');
        $this->assess('moderate', 55, 'Modone');
        $this->assess('low', 15, 'Lowone');
    }

    public function test_the_default_list_shows_high_and_moderate_only(): void
    {
        $this->threeStudents();

        $page = $this->actingAs($this->counselor())->get(route('admin.risk.index'));

        $this->assertSame(['Highone', 'Modone'], $this->names($page));
    }

    public function test_include_low_shows_everyone(): void
    {
        $this->threeStudents();

        $page = $this->actingAs($this->counselor())->get(route('admin.risk.index', ['include_low' => 1]));

        $this->assertSame(['Highone', 'Lowone', 'Modone'], $this->names($page));
    }

    public function test_choosing_a_specific_level_overrides_the_default(): void
    {
        $this->threeStudents();
        $c = $this->counselor();

        $this->assertSame(['Lowone'], $this->names($this->actingAs($c)->get(route('admin.risk.index', ['risk_level' => 'low']))));
        $this->assertSame(['Highone'], $this->names($this->actingAs($c)->get(route('admin.risk.index', ['risk_level' => 'high']))));
        $this->assertSame(['Modone'], $this->names($this->actingAs($c)->get(route('admin.risk.index', ['risk_level' => 'moderate']))));
    }

    public function test_the_first_card_counts_at_risk_students_and_the_page_has_one_name(): void
    {
        $this->threeStudents();

        $page = $this->actingAs($this->counselor())->get(route('admin.risk.index'));
        $html = $page->getContent();

        $this->assertStringContainsString('At Risk', $html);
        $this->assertStringContainsString('of 3 assessed', $html);
        $this->assertSame(3, $page->viewData('totalAssessed'), 'the assessed total is still available');
        $this->assertSame(1, $page->viewData('lowRiskCount'));
        $this->assertStringNotContainsString('Early Warning System', $html);
        $this->assertStringContainsString('Students the early-warning engine rates High or Moderate risk', $html);
    }

    public function test_the_list_agrees_with_the_students_page_at_risk_count(): void
    {
        $this->threeStudents();
        $c = $this->counselor();

        $risk = $this->actingAs($c)->get(route('admin.risk.index'))->viewData('assessments')->total();
        $students = $this->actingAs($c)->get(route('admin.students.index'))->viewData('atRiskStudents');

        $this->assertSame($students, $risk);
    }

    public function test_an_attention_group_defines_its_own_population_so_counts_still_match_the_list(): void
    {
        // A LOW student whose score jumped 23 points is "rising": the dashboard counts them, so the list must show them.
        $s = Student::factory()->create(['first_name' => 'Risingone']);
        RiskAssessment::create(['student_id' => $s->id, 'risk_score' => 5, 'risk_level' => 'low', 'assessed_at' => now()->subDays(3)]);
        RiskAssessment::create(['student_id' => $s->id, 'risk_score' => 28, 'risk_level' => 'low', 'assessed_at' => now()]);
        $c = $this->counselor();

        $page = $this->actingAs($c)->get(route('admin.risk.index', ['attention' => 'rising']));

        $this->assertSame(['Risingone'], $this->names($page));
        $this->assertSame(1, $page->viewData('attentionCounts')['rising']);
    }

    public function test_the_export_matches_the_default_view_and_include_low(): void
    {
        $this->threeStudents();
        $c = $this->counselor();

        $default = $this->actingAs($c)->get(route('admin.risk.export'))->streamedContent();
        $all = $this->actingAs($c)->get(route('admin.risk.export', ['include_low' => 1]))->streamedContent();

        $this->assertStringContainsString('Highone', $default);
        $this->assertStringContainsString('Modone', $default);
        $this->assertStringNotContainsString('Lowone', $default);
        $this->assertStringContainsString('Lowone', $all);
    }

    public function test_the_page_offers_the_toggle_and_the_export_link_carries_it(): void
    {
        $this->threeStudents();

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.index', ['include_low' => 1]))->getContent();

        $this->assertStringContainsString('name="include_low"', $html);
        $this->assertMatchesRegularExpression('/name="include_low" value="1" checked/', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString(e(route('admin.risk.export', ['include_low' => 1])), $html);
    }

    public function test_the_default_view_keeps_scope_search_and_sorting(): void
    {
        $c = $this->counselor();
        $mine = $this->assess('moderate', 60, 'Mineone');
        Referral::factory()->create(['student_id' => $mine->id, 'counselor_id' => $c->id]);
        $theirs = $this->assess('high', 95, 'Theirsone');
        Referral::factory()->create(['student_id' => $theirs->id, 'counselor_id' => $this->counselor()->id]);
        $this->assess('low', 10, 'Lowmine');

        $this->assertSame(['Mineone'], $this->names($this->actingAs($c)->get(route('admin.risk.index', ['scope' => 'mine']))));
        $this->assertSame(['Theirsone'], $this->names($this->actingAs($c)->get(route('admin.risk.index', ['search' => 'Theirs']))));
        $this->assertSame(['Theirsone', 'Mineone'], $this->actingAs($c)->get(route('admin.risk.index'))->viewData('assessments')->map(fn ($a) => $a->student->first_name)->all());
    }
}
