<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layout guards for the At-Risk profile and the Student page: slim alert
 * strips, columns that end on a common edge (no big empty area at the bottom
 * of the shorter one), and a history chart whose bars actually have height.
 */
class ProfileLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    /** A flagged student with an open referral and a few assessments. */
    private function flagged(int $assessments = 3): Student
    {
        $s = Student::factory()->create();
        for ($i = 0; $i < $assessments; $i++) {
            RiskAssessment::create([
                'student_id' => $s->id, 'risk_score' => 70 + $i * 5, 'risk_level' => 'high',
                'assessed_at' => now()->subDays($assessments - $i),
                'risk_factors' => ['reason' => 'He threatened to stab a classmate.', 'recommended_seminar_tag' => 'anti_bullying'],
            ]);
        }
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'pending', 'counselor_id' => null, 'reason' => 'He threatened to stab a classmate.']);

        return $s;
    }

    private function profile(Student $s): string
    {
        return $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();
    }

    private function strip(string $html, string $marker): string
    {
        preg_match('#<div class="[^"]*"\s+' . preg_quote($marker, '#') . '.*?</div>\s*</div>|<div class="[^"]*" ' . preg_quote($marker, '#') . '.*?</p>\s*</div>#s', $html, $m);

        return $m[0] ?? '';
    }

    // ── Slim alert strips ────────────────────────────────────────────────

    public function test_the_safety_flag_is_a_slim_two_line_strip_with_the_link_on_the_heading_row(): void
    {
        $s = $this->flagged();

        foreach ([$this->profile($s), $this->actingAs($this->counselor())->get(route('admin.students.show', $s->id))->getContent()] as $html) {
            $strip = $this->strip($html, 'data-safety-flag');

            $this->assertNotSame('', $strip);
            $this->assertStringContainsString('px-2.5 py-1.5', $strip, 'tight padding');
            $this->assertStringContainsString('ml-auto', $strip, 'the link sits on the heading row');
            $this->assertStringContainsString('text-[11px]', $strip, 'small detail text');
            $this->assertStringNotContainsString('Review it before anything else', $strip, 'the extra sentence is gone');
            $this->assertStringContainsString('mentions "stab"', html_entity_decode($strip));
        }
    }

    public function test_the_case_status_is_a_slim_strip_with_the_referral_link_on_the_heading_row(): void
    {
        $s = $this->flagged();

        foreach ([$this->profile($s), $this->actingAs($this->counselor())->get(route('admin.students.show', $s->id))->getContent()] as $html) {
            preg_match('#data-case-status="[a-z_]+".*?</p>#s', $html, $m);

            $this->assertStringContainsString('ml-auto', $m[0]);
            $this->assertStringContainsString('View referral #', $m[0]);
            $this->assertStringNotContainsString('mt-1.5 text-[11px]', $m[0], 'no separate link line under the text');
        }
    }

    public function test_the_strips_no_longer_use_the_old_roomy_box_styling(): void
    {
        $html = $this->profile($this->flagged());

        $this->assertStringNotContainsString('rounded-xl border border-red-200 bg-red-50 px-3 py-2.5', $html);
        $this->assertStringNotContainsString('inline-block mt-1.5 text-[11px] font-semibold', $html);
    }

    // ── Profile columns ──────────────────────────────────────────────────

    public function test_why_the_student_is_flagged_leads_the_right_column_and_the_left_keeps_the_actions(): void
    {
        $html = $this->profile($this->flagged());

        $split = strpos($html, 'lg:col-span-2');
        $left = substr($html, 0, $split);
        $right = substr($html, $split);

        $this->assertStringContainsString('Risk Status Overview', $left);
        $this->assertStringContainsString('Review &amp; Override', $left);
        $this->assertStringNotContainsString('Risk Assessment Details', $left, 'moved out of the (longer) left column');

        $order = array_map(fn ($h) => strpos($right, $h), ['Risk Assessment Details', 'Case Summary', 'Assessment Log', 'Risk Assessment History']);
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'details, then summary, then log, then history');
    }

    public function test_both_columns_are_flex_columns_whose_last_card_grows_to_the_common_bottom_edge(): void
    {
        $html = $this->profile($this->flagged());

        $this->assertStringContainsString('lg:col-span-1 flex flex-col gap-6', $html);
        $this->assertStringContainsString('lg:col-span-2 flex flex-col gap-6', $html);
        $this->assertStringNotContainsString('lg:col-span-1 space-y-6', $html);
        $this->assertMatchesRegularExpression('#rounded-2xl shadow-sm overflow-hidden flex-1 flex flex-col">\s*<div class="p-6 flex flex-col flex-1">#', $html, 'Review & Override stretches');
        $this->assertMatchesRegularExpression('#shadow-sm p-6 flex-1 flex flex-col">\s*<h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">\s*<i class="ti ti-chart-line#', $html, 'the chart card stretches');
    }

    public function test_the_save_button_is_pinned_to_the_bottom_of_the_review_card(): void
    {
        $html = $this->profile($this->flagged());

        $this->assertStringContainsString('flex flex-col gap-3 flex-1', $html);
        $this->assertMatchesRegularExpression('#py-2 mt-auto bg-gray-900[^>]*>Save review#', $html);
    }

    // ── History chart ────────────────────────────────────────────────────

    public function test_the_history_bars_sit_in_a_layer_with_a_definite_height_so_they_render(): void
    {
        $html = $this->profile($this->flagged(3));

        // The bars' heights are percentages: they only resolve inside a box with a definite height.
        // They used to sit in an auto-height column and rendered at 0px.
        $this->assertStringContainsString('data-history-chart', $html);
        $this->assertMatchesRegularExpression('#data-history-chart>\s*<div class="absolute inset-0 pl-4 pb-6 flex items-end justify-around gap-2">#', $html);
        $this->assertSame(3, substr_count($html, 'flex-1 max-w-[4.5rem] h-full flex flex-col justify-end'), 'one full-height column per bar');
        $this->assertStringNotContainsString('relative h-48 w-full flex items-end', $html, 'the old auto-height container is gone');
    }

    public function test_each_bar_carries_its_score_as_a_percentage_height(): void
    {
        $html = $this->profile($this->flagged(2));

        preg_match_all('#rounded-t-sm[^"]*"[^>]*style="height: ([0-9.]+)%#', $html, $m);

        $this->assertSame(['70', '75'], $m[1]);
    }

    public function test_a_single_assessment_still_shows_the_not_enough_data_message(): void
    {
        $html = $this->profile($this->flagged(1));

        $this->assertStringContainsString('Not enough historical data to generate trend chart.', $html);
        $this->assertStringNotContainsString('rounded-t-sm', $html);
    }

    // ── Student page ─────────────────────────────────────────────────────

    public function test_the_student_record_card_stretches_to_the_bottom_of_the_row(): void
    {
        $s = $this->flagged();

        $html = $this->actingAs($this->counselor())->get(route('admin.students.show', $s->id))->getContent();

        $this->assertStringContainsString('lg:col-span-2 flex flex-col gap-6', $html);
        $this->assertMatchesRegularExpression('#hover:shadow-hover print:shadow-none print:border-transparent print:col-span-full flex flex-col flex-1"#', $html);
        $this->assertStringNotContainsString('flex flex-col self-start', $html, 'no longer left short at the top of the column');
    }

    public function test_the_profile_pages_still_render_for_a_student_with_nothing_flagged(): void
    {
        $s = Student::factory()->create();
        RiskAssessment::create(['student_id' => $s->id, 'risk_score' => 20, 'risk_level' => 'low', 'assessed_at' => now()]);
        $c = $this->counselor();

        $this->actingAs($c)->get(route('admin.risk.show', $s->id))->assertOk()->assertDontSee('data-safety-flag', false);
        $this->actingAs($c)->get(route('admin.students.show', $s->id))->assertOk()->assertDontSee('data-safety-flag', false);
    }
}
