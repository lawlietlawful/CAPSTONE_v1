<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layout guards for Intervention Details. The left column held only the
 * session record while the right stacked four cards (~780px of empty space
 * under the record). Update Session now sits under the record it updates,
 * the columns end on a common edge, and Risk Trend is a slim strip below.
 */
class InterventionShowLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function assessment(Student $student, float $score): RiskAssessment
    {
        return RiskAssessment::create([
            'student_id' => $student->id, 'previous_referrals_count' => 1, 'behavioral_reports_count' => 0,
            'concern_type_encoded' => 1, 'days_since_last_referral' => 10, 'risk_score' => $score,
            'risk_level' => 'moderate', 'risk_factors' => ['reason' => 'test'], 'assessed_at' => now(),
        ]);
    }

    private function page(User $viewer, Intervention $iv): string
    {
        return $this->actingAs($viewer)->get(route('counselor.interventions.show', $iv->id))->getContent();
    }

    private function mine(User $c, array $referral = [], array $iv = []): Intervention
    {
        $r = Referral::factory()->create($referral);

        return Intervention::factory()->create(array_merge(['referral_id' => $r->id, 'counselor_id' => $c->id], $iv));
    }

    /** [left column html, right column html, everything after the grid]. */
    private function columns(string $html): array
    {
        $left = strpos($html, 'lg:col-span-2 flex flex-col gap-6');
        $right = strpos($html, 'lg:col-span-1 flex flex-col gap-6');
        $this->assertNotFalse($left);
        $this->assertNotFalse($right);
        $this->assertLessThan($right, $left);

        return [substr($html, $left, $right - $left), substr($html, $right), $html];
    }

    public function test_update_session_sits_in_the_left_column_under_the_session_record(): void
    {
        $c = $this->counselor();
        [$left, $right] = $this->columns($this->page($c, $this->mine($c)));

        $this->assertStringContainsString('Session Record', $left);
        $this->assertStringContainsString('data-update-session', $left);
        $this->assertGreaterThan(strpos($left, 'Session Record'), strpos($left, 'data-update-session'), 'under the record it updates');
        $this->assertStringNotContainsString('data-update-session', $right, 'no longer the fourth card of the right column');
        $this->assertStringNotContainsString('Update Session', $right);
    }

    public function test_the_update_form_is_laid_out_across_the_wide_column(): void
    {
        $c = $this->counselor();
        [$left] = $this->columns($this->page($c, $this->mine($c)));

        $this->assertStringContainsString('grid grid-cols-1 md:grid-cols-3 gap-4 items-end', $left);
        $this->assertStringContainsString('name="outcome"', $left);
        $this->assertStringContainsString('name="follow_up_date"', $left);
        $this->assertStringContainsString('Update Session', $left);
    }

    public function test_the_update_form_still_targets_the_quick_update_route_and_confirms_resolving(): void
    {
        $c = $this->counselor();
        $iv = $this->mine($c);

        $html = $this->page($c, $iv);

        $this->assertStringContainsString(route('counselor.interventions.quickUpdate', $iv->id), $html);
        $this->assertStringContainsString('confirmIfResolving()', $html);
        $this->assertStringContainsString('Mark as Resolved?', $html);
    }

    public function test_another_counselors_session_has_no_update_form_anywhere(): void
    {
        $viewer = $this->counselor();
        $owner = $this->counselor();

        $html = $this->page($viewer, $this->mine($owner));

        $this->assertStringNotContainsString('data-update-session', $html);
        $this->assertStringNotContainsString('name="follow_up_date"', $html);
    }

    public function test_the_right_column_holds_the_student_and_the_linked_referral_only(): void
    {
        $c = $this->counselor();
        [, $right] = $this->columns($this->page($c, $this->mine($c)));
        $right = substr($right, 0, strpos($right, 'data-risk-trend') ?: strlen($right));

        $this->assertLessThan(strpos($right, 'Linked Referral'), strpos($right, 'Student Profile'));
        $this->assertStringNotContainsString('Risk Trend', $right);
    }

    public function test_both_columns_are_flex_columns_and_the_last_right_card_grows_to_the_common_edge(): void
    {
        $c = $this->counselor();
        $html = $this->page($c, $this->mine($c));

        $this->assertStringNotContainsString('lg:col-span-2 space-y-6', $html);
        $this->assertStringNotContainsString('lg:col-span-1 space-y-6', $html);
        $this->assertMatchesRegularExpression('#shadow-sm overflow-hidden flex-1 flex flex-col">\s*<div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">\s*<h3 class="font-semibold text-gray-800 flex items-center gap-2">\s*<i class="ti ti-file-text#', $html, 'Linked Referral stretches');
        $this->assertStringContainsString('mt-auto pt-4 border-t border-gray-100 text-center', $html, 'its link stays pinned to the card foot');
    }

    public function test_the_student_profile_is_compact_with_the_avatar_beside_the_name(): void
    {
        $c = $this->counselor();
        $student = Student::factory()->create(['first_name' => 'Angelo', 'last_name' => 'Fernandez']);
        $iv = $this->mine($c, ['student_id' => $student->id]);

        $html = $this->page($c, $iv);

        $this->assertMatchesRegularExpression('#flex items-center gap-4">\s*<div class="w-14 h-14 rounded-full#', $html);
        $this->assertStringContainsString('Angelo Fernandez', $html);
        $this->assertStringContainsString('ID: ' . $student->student_id_number, $html);
        $this->assertStringContainsString('grid grid-cols-2 gap-4 text-sm', $html, 'course and year on one row');
        $this->assertStringNotContainsString('w-16 h-16 rounded-full', $html, 'the tall centred avatar is gone');
    }

    public function test_risk_trend_is_a_slim_full_width_strip_after_the_columns(): void
    {
        $c = $this->counselor();
        $student = Student::factory()->create();
        $a = $this->assessment($student, 80);
        $iv = $this->mine($c, ['student_id' => $student->id, 'risk_assessment_id' => $a->id]);

        $html = $this->page($c, $iv);

        $this->assertSame(1, substr_count($html, 'data-risk-trend'));
        $this->assertGreaterThan(strpos($html, 'Linked Referral'), strpos($html, 'data-risk-trend'), 'after both columns');
        $this->assertStringContainsString('No newer AI assessment yet', $html);
        $this->assertStringContainsString('80', $html);
        $this->assertStringContainsString('mt-6 bg-white border border-gray-100 rounded-2xl', $html);
    }

    public function test_the_risk_trend_strip_still_reports_improved_and_worsened(): void
    {
        $c = $this->counselor();

        $student = Student::factory()->create();
        $first = $this->assessment($student, 80);
        $iv = $this->mine($c, ['student_id' => $student->id, 'risk_assessment_id' => $first->id]);
        $this->assessment($student, 50);
        $this->assertStringContainsString('Risk Improved', $this->page($c, $iv));

        $other = Student::factory()->create();
        $base = $this->assessment($other, 40);
        $iv2 = $this->mine($c, ['student_id' => $other->id, 'risk_assessment_id' => $base->id]);
        $this->assessment($other, 70);
        $this->assertStringContainsString('Risk Worsened', $this->page($c, $iv2));
    }

    public function test_no_risk_trend_strip_when_the_referral_was_never_assessed(): void
    {
        $c = $this->counselor();

        $html = $this->page($c, $this->mine($c, ['risk_assessment_id' => null]));

        $this->assertStringNotContainsString('data-risk-trend', $html);
        $this->assertStringNotContainsString('Risk Trend', $html);
    }

    public function test_other_interventions_stay_in_the_left_column_below_the_update_form(): void
    {
        $c = $this->counselor();
        $student = Student::factory()->create();
        $referral = Referral::factory()->create(['student_id' => $student->id]);
        $current = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $c->id]);
        Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $c->id, 'intervention_type' => 'Group Counseling']);

        [$left, $right] = $this->columns($this->page($c, $current));

        $this->assertStringContainsString("This Student's Other Interventions", html_entity_decode($left));
        $this->assertGreaterThan(strpos($left, 'data-update-session'), strpos(html_entity_decode($left), "This Student's Other Interventions"));
        $this->assertStringNotContainsString('Other Interventions', $right);
    }

    public function test_the_page_still_renders_for_every_outcome_and_overdue_state(): void
    {
        $c = $this->counselor();

        foreach ([null, 'improving', 'no_change', 'worsening', 'resolved'] as $outcome) {
            $iv = $this->mine($c, [], ['outcome' => $outcome, 'follow_up_date' => today()->subDays(3)]);
            $this->actingAs($c)->get(route('counselor.interventions.show', $iv->id))->assertOk();
        }
    }
}
