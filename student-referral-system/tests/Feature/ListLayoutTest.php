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
 * Layout guards for the two lists that carry actions: rows must line up.
 * The Reports list used to put a referral chip OR a create icon next to the
 * view icon, so the icons shifted from row to row; the At-Risk student cell
 * crowded the ID, a solid red badge and a blue badge onto one line.
 */
class ListLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    /** The table rows of the first <tbody>, as arrays of their <td> HTML. */
    private function rows(string $html): array
    {
        preg_match('#<tbody.*?</tbody>#s', $html, $body);
        preg_match_all('#<tr[^>]*>(.*?)</tr>#s', $body[0] ?? '', $trs);

        return array_map(function ($tr) {
            preg_match_all('#<td[^>]*>(.*?)</td>#s', $tr, $tds);

            return $tds[1];
        }, $trs[1]);
    }

    // ── Reports list ─────────────────────────────────────────────────────

    private function mixedReports(): void
    {
        $linked = BehavioralReport::factory()->create(['status' => 'pending']);
        Referral::factory()->create(['behavioral_report_id' => $linked->id, 'student_id' => $linked->student_id, 'status' => 'in_progress']);
        BehavioralReport::factory()->create(['status' => 'pending']);
        BehavioralReport::factory()->create(['status' => 'resolved']);
    }

    public function test_the_reports_list_has_a_referral_column_after_status(): void
    {
        $html = $this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.index'))->getContent();
        preg_match('#<thead.*?</thead>#s', $html, $head);
        $headers = array_map(fn ($h) => trim(strip_tags($h)), preg_split('#</th>#', $head[0]));

        $status = array_search('Status', $headers);
        $this->assertNotFalse($status);
        $this->assertSame('Referral', $headers[$status + 1]);
        $this->assertSame('Action', $headers[count($headers) - 2], 'Action stays the last column');
    }

    public function test_every_report_row_has_the_same_cells_whatever_its_referral_state(): void
    {
        $this->mixedReports();

        $rows = $this->rows($this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.index'))->getContent());

        $this->assertCount(3, $rows);
        $this->assertCount(1, array_unique(array_map('count', $rows)), 'all rows have the same number of cells');
        $this->assertSame(9, count($rows[0]), 'checkbox + 7 columns + referral');
    }

    public function test_the_action_column_holds_only_the_view_button_on_every_row(): void
    {
        $this->mixedReports();

        $rows = $this->rows($this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.index'))->getContent());

        foreach ($rows as $cells) {
            $action = end($cells);
            $this->assertSame(1, substr_count($action, '<a '), 'a single icon, so it sits in the same place on every row');
            $this->assertStringContainsString('ti-eye', $action);
            $this->assertStringNotContainsString('data-quick-refer', $action);
            $this->assertStringNotContainsString('data-linked-referral', $action);
        }
    }

    public function test_the_referral_cell_shows_a_status_pill_a_create_button_or_a_dash(): void
    {
        $this->mixedReports();

        $rows = $this->rows($this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.index'))->getContent());
        $cells = array_map(fn ($r) => $r[5], $rows); // checkbox, student, incident, severity, status, REFERRAL

        $pill = collect($cells)->first(fn ($c) => str_contains($c, 'data-linked-referral'));
        $create = collect($cells)->first(fn ($c) => str_contains($c, 'data-quick-refer'));
        $dash = collect($cells)->first(fn ($c) => ! str_contains($c, 'data-linked-referral') && ! str_contains($c, 'data-quick-refer'));

        $this->assertMatchesRegularExpression('/#\d+\s*<span[^>]*>&middot;<\/span>\s*In Progress/', $pill);
        $this->assertStringContainsString('Create referral', $create);
        $this->assertStringContainsString('&mdash;', $dash);
    }

    public function test_the_empty_reports_table_spans_every_column(): void
    {
        $html = $this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.index'))->getContent();

        $this->assertStringContainsString('colspan="9"', $html);
    }

    // ── At-Risk list ─────────────────────────────────────────────────────

    private function riskRow(string $level = 'moderate', bool $flag = false, bool $openReferral = false, string $name = 'Rowone'): Student
    {
        $s = Student::factory()->create(['first_name' => $name]);
        RiskAssessment::create(['student_id' => $s->id, 'risk_score' => $level === 'high' ? 90 : 60, 'risk_level' => $level, 'assessed_at' => now()]);
        if ($flag || $openReferral) {
            Referral::factory()->create([
                'student_id' => $s->id, 'status' => $openReferral ? 'pending' : 'resolved',
                'reason' => $flag ? 'He threatened to stab a classmate.' : 'Late to class.',
            ]);
        }

        return $s;
    }

    public function test_the_student_cell_stacks_name_then_id_then_a_badge_row(): void
    {
        $this->riskRow('moderate', true, true);

        $rows = $this->rows($this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent());
        $cell = $rows[0][1];

        $namePos = strpos($cell, 'Rowone');
        $idPos = strpos($cell, 'text-xs text-gray-500 mt-0.5');
        $badgesPos = strpos($cell, 'data-student-badges');

        $this->assertTrue($namePos < $idPos && $idPos < $badgesPos, 'name, then ID, then badges');
        $this->assertStringContainsString('flex flex-wrap', substr($cell, $badgesPos - 60, 120), 'badges wrap on their own row');
    }

    public function test_badges_are_uniform_soft_pills_not_a_solid_block(): void
    {
        $this->riskRow('moderate', true, true);

        $cell = $this->rows($this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent())[0][1];

        $this->assertSame(2, substr_count($cell, 'h-5 px-2 rounded-full'), 'both pills share height and shape');
        $this->assertStringNotContainsString('bg-red-600', $cell, 'the safety flag is no longer a solid red block');
        $this->assertStringContainsString('Safety flag', $cell);
        $this->assertStringContainsString('Action taken', $cell);
    }

    public function test_a_student_with_no_badges_gets_no_empty_badge_row(): void
    {
        $this->riskRow('moderate', false, false);

        $cell = $this->rows($this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent())[0][1];

        $this->assertStringNotContainsString('data-student-badges', $cell);
    }

    public function test_only_the_relevant_badge_shows(): void
    {
        $this->riskRow('moderate', true, false, 'Flagonly');  // flagged (referral resolved? flag needs OPEN case) -> use open
        $flagOnlyStudent = Student::where('first_name', 'Flagonly')->first();
        Referral::where('student_id', $flagOnlyStudent->id)->update(['status' => 'pending']);

        $cell = $this->rows($this->actingAs($this->counselor())->get(route('admin.risk.index', ['search' => 'Flagonly']))->getContent())[0][1];

        $this->assertSame(2, substr_count($cell, 'rounded-full'), 'an open case both flags the student and counts as action taken');

        $plain = $this->riskRow('high', false, true, 'Actiononly');
        $cell = $this->rows($this->actingAs($this->counselor())->get(route('admin.risk.index', ['search' => 'Actiononly']))->getContent())[0][1];
        $this->assertSame(1, substr_count($cell, 'rounded-full'));
        $this->assertStringNotContainsString('Safety flag', $cell);
    }

    public function test_the_action_buttons_share_one_size_and_stack_centered(): void
    {
        $this->riskRow('high', false, false);                // Details + Open referral
        $this->riskRow('moderate', false, true, 'Covered');  // Details only

        $rows = $this->rows($this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent());

        foreach ($rows as $cells) {
            $action = end($cells);
            $this->assertStringContainsString('flex flex-col items-center', $action);
            $this->assertStringContainsString('Details', $action);
            $this->assertSame(substr_count($action, 'w-28 h-8'), substr_count($action, '<a ') + substr_count($action, '<button'), 'every action control is the same size');
        }

        $both = collect($rows)->first(fn ($c) => str_contains(end($c), 'data-quick-refer'));
        $this->assertSame(2, substr_count(end($both), 'w-28 h-8'));
        $this->assertStringContainsString('Open referral', end($both));
    }

    public function test_the_dashboard_watchlist_uses_the_same_pill(): void
    {
        $this->riskRow('moderate', true, true, 'Watched');

        $html = $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->getContent();

        $this->assertStringContainsString('border-red-300 bg-red-50 text-red-700', $html);
        $this->assertStringNotContainsString('bg-red-600 text-white', $html);
    }
}
