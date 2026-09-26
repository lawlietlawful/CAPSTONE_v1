<?php

namespace Tests\Feature;

use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Risk Score cell: the number is always centred on its own line (equal
 * slots either side hold the trend arrow), and special cases sit as labelled
 * pills UNDER it instead of squeezing in beside it and shifting the number.
 */
class RiskScoreCellTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function assess(Student $s, float $score, ?array $factors = null, $at = null, string $level = 'moderate'): RiskAssessment
    {
        return RiskAssessment::create([
            'student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level,
            'assessed_at' => $at ?? now(), 'risk_factors' => $factors,
        ]);
    }

    /** The score cells of every row on the (default) At-Risk list, in order. */
    private function cells(): array
    {
        $html = $this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent();
        preg_match_all('#<div class="flex flex-col items-center gap-1" data-score-cell>(.*?)</td>#s', $html, $m);

        return $m[1];
    }

    public function test_the_number_line_has_the_same_structure_on_every_row(): void
    {
        $plain = Student::factory()->create();
        $this->assess($plain, 60);
        $manual = Student::factory()->create();
        $this->assess($manual, 61, ['source' => 'override', 'override' => ['by_name' => 'Ma\'am Edago', 'note' => 'Reviewed']]);
        $trending = Student::factory()->create();
        $this->assess($trending, 50, null, now()->subDay());
        $this->assess($trending, 70);

        $cells = $this->cells();

        $this->assertCount(3, $cells);
        foreach ($cells as $cell) {
            preg_match('#data-score-line>(.*?)</div>#s', $cell, $line);
            $this->assertSame(2, substr_count($line[1], 'w-5'), 'a spacer and a trend slot of equal width flank the number');
            $this->assertMatchesRegularExpression('#font-mono font-medium text-gray-900">\d+\.\d</span>#', $line[1]);
        }
    }

    public function test_the_trend_arrow_appears_only_when_the_score_moved(): void
    {
        $up = Student::factory()->create();
        $this->assess($up, 50, null, now()->subDay());
        $this->assess($up, 58.5);
        $down = Student::factory()->create();
        $this->assess($down, 70, null, now()->subDay(), 'high');
        $this->assess($down, 65);
        $same = Student::factory()->create();
        $this->assess($same, 55, null, now()->subDay());
        $this->assess($same, 55);
        $first = Student::factory()->create();
        $this->assess($first, 52);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent();

        $this->assertSame(1, substr_count($html, 'data-trend="up"'));
        $this->assertSame(1, substr_count($html, 'data-trend="down"'));
        $this->assertStringContainsString('+8.5 since last assessment', $html);
        $this->assertStringContainsString('-5.0 since last assessment', $html);
        $this->assertStringNotContainsString('No change', $html, 'an unchanged score gets no mark at all');
        $this->assertStringNotContainsString('ti-minus', $html);
    }

    public function test_a_manual_review_is_a_labelled_pill_under_the_number(): void
    {
        $s = Student::factory()->create();
        $this->assess($s, 94.1, ['source' => 'override', 'override' => ['by_name' => 'Ma\'am Edago', 'note' => 'Met the family']], null, 'high');

        $cell = $this->cells()[0];

        $this->assertStringContainsString('Manual review', $cell);
        $this->assertStringContainsString('data-manual-chip', $cell);
        $this->assertStringContainsString('Set manually by Ma&#039;am Edago: Met the family', $cell);
        $this->assertGreaterThan(strpos($cell, 'data-score-line'), strpos($cell, 'data-manual-chip'), 'below the number, not beside it');
        $this->assertStringNotContainsString('>manual<', $cell, 'the cryptic lowercase tag is gone');
    }

    public function test_a_held_score_is_a_labelled_pill_naming_the_referral(): void
    {
        $s = Student::factory()->create();
        $this->assess($s, 94.1, ['held_by_referral_id' => 82, 'ml_risk_score' => 30, 'ml_risk_level' => 'low'], null, 'high');

        $cell = $this->cells()[0];

        $this->assertStringContainsString('data-held-chip', $cell);
        $this->assertMatchesRegularExpression('#Held\s*&middot;\s*\#82#', $cell);
        $this->assertStringContainsString('Held by open referral #82', $cell);
        $this->assertStringContainsString('alone scored 30.0 (Low)', $cell);
        $this->assertStringNotContainsString('>held<', $cell);
    }

    public function test_held_and_manual_stack_in_one_notes_block(): void
    {
        $s = Student::factory()->create();
        $this->assess($s, 80, ['source' => 'override', 'held_by_referral_id' => 7, 'override' => ['by_name' => 'X', 'note' => 'y']], null, 'high');

        $cell = $this->cells()[0];

        $this->assertSame(1, substr_count($cell, 'data-score-notes'));
        $this->assertSame(1, substr_count($cell, 'data-held-chip'));
        $this->assertSame(1, substr_count($cell, 'data-manual-chip'));
        $this->assertStringContainsString('flex flex-col items-center', substr($cell, strpos($cell, 'data-score-notes') - 60, 90));
    }

    public function test_an_ordinary_score_has_no_notes_block_at_all(): void
    {
        $this->assess(Student::factory()->create(), 60);

        $cell = $this->cells()[0];

        $this->assertStringNotContainsString('data-score-notes', $cell);
        $this->assertStringNotContainsString('data-manual-chip', $cell);
        $this->assertStringNotContainsString('data-held-chip', $cell);
    }

    public function test_the_notes_text_is_escaped(): void
    {
        $s = Student::factory()->create();
        $this->assess($s, 80, ['source' => 'override', 'override' => ['by_name' => '<script>alert(1)</script>', 'note' => '"><img src=x>']], null, 'high');

        $cell = $this->cells()[0];

        $this->assertStringNotContainsString('<script>alert(1)</script>', $cell);
        $this->assertStringNotContainsString('<img src=x>', $cell);
    }
}
