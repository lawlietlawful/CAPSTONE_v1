<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use App\Services\BehavioralReportService;
use App\Support\SafetyFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A student whose open case names violence, a weapon or a threat must never
 * sit unmarked at "Moderate 61" on the risk views. The flag reuses the safety
 * net's own keyword list and only lasts while the case is unresolved.
 */
class SafetyFlagTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function assess(Student $s, string $level = 'moderate', float $score = 60, $at = null): RiskAssessment
    {
        return RiskAssessment::create(['student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level, 'assessed_at' => $at ?? now()]);
    }

    private function referralFor(Student $s, string $reason, string $status = 'pending'): Referral
    {
        return Referral::factory()->create(['student_id' => $s->id, 'reason' => $reason, 'status' => $status, 'counselor_id' => null]);
    }

    private function stabber(): Student
    {
        $s = Student::factory()->create(['first_name' => 'Stabby']);
        $this->assess($s, 'moderate', 60.8);
        $this->referralFor($s, 'During recess, he threatened to stab his classmate.');

        return $s;
    }

    // ── The detector ─────────────────────────────────────────────────────

    public function test_it_finds_violence_weapon_and_threat_words_including_cebuano(): void
    {
        $this->assertSame('stab', SafetyFlags::keywordIn('He wanted to stab a classmate'));
        $this->assertSame('knife', SafetyFlags::keywordIn('Brought a KNIFE to school'));
        $this->assertSame('naghulga', SafetyFlags::keywordIn('Naghulga siya sa iyang classmate'));
        $this->assertNull(SafetyFlags::keywordIn('Late to class and talked during the lesson.'));
        $this->assertNull(SafetyFlags::keywordIn(null));
    }

    public function test_it_matches_whole_words_only(): void
    {
        $this->assertNull(SafetyFlags::keywordIn('She wore a white shirt and had a hitch in her walk.'), '"hit" inside another word is not a match');
        $this->assertNull(SafetyFlags::keywordIn('Bring your gunny sack tomorrow.'));
    }

    public function test_system_written_bracket_tags_never_flag_a_case_on_their_own(): void
    {
        $this->assertNull(SafetyFlags::keywordIn('[AUTO-ESCALATED from Behavioral Report #4] [Flagged: description names violence/a weapon/a threat] Talked during class.'));
        $this->assertNull(SafetyFlags::keywordIn('[From Behavioral Report #9] Missed three days of school.'));
        $this->assertSame('threatened', SafetyFlags::keywordIn('[AUTO-ESCALATED from Behavioral Report #4] He threatened a teacher.'), 'the real text after the tags still counts');
    }

    public function test_it_uses_the_same_keyword_list_as_the_escalation_rule(): void
    {
        foreach (BehavioralReportService::VIOLENCE_KEYWORDS as $keyword) {
            $this->assertNotNull(SafetyFlags::keywordIn("They said {$keyword} during recess."), $keyword);
        }
    }

    // ── Which students are flagged ───────────────────────────────────────

    public function test_an_open_referral_flags_the_student_and_names_the_source(): void
    {
        $s = $this->stabber();

        $flags = SafetyFlags::forStudents();

        $this->assertSame(['keyword' => 'stab', 'source' => 'referral', 'id' => Referral::where('student_id', $s->id)->value('id')], $flags[$s->id]);
        $this->assertStringContainsString('mentions "stab"', SafetyFlags::describe($flags[$s->id]));
    }

    public function test_an_unresolved_report_flags_the_student_even_without_a_referral(): void
    {
        $s = Student::factory()->create();
        $report = BehavioralReport::factory()->create(['student_id' => $s->id, 'status' => 'reviewed', 'description' => 'Showed classmates a knife.']);

        $flags = SafetyFlags::forStudents([$s->id]);

        $this->assertSame('report', $flags[$s->id]['source']);
        $this->assertSame($report->id, $flags[$s->id]['id']);
    }

    public function test_a_resolved_or_cancelled_case_no_longer_flags(): void
    {
        $a = Student::factory()->create();
        $this->referralFor($a, 'He threatened a classmate.', 'resolved');
        $b = Student::factory()->create();
        $this->referralFor($b, 'He threatened a classmate.', 'cancelled');
        $c = Student::factory()->create();
        BehavioralReport::factory()->create(['student_id' => $c->id, 'status' => 'resolved', 'description' => 'He threatened a classmate.']);

        $this->assertSame([], SafetyFlags::forStudents());
    }

    public function test_the_flag_lifts_when_the_case_is_resolved(): void
    {
        $s = $this->stabber();
        $this->assertArrayHasKey($s->id, SafetyFlags::forStudents());

        Referral::where('student_id', $s->id)->update(['status' => 'resolved']);

        $this->assertArrayNotHasKey($s->id, SafetyFlags::forStudents());
    }

    public function test_a_referral_is_preferred_over_a_report_and_lookups_can_be_limited(): void
    {
        $s = $this->stabber();
        BehavioralReport::factory()->create(['student_id' => $s->id, 'status' => 'pending', 'description' => 'Also had a knife.']);
        $other = $this->stabber();

        $limited = SafetyFlags::forStudents([$s->id]);

        $this->assertSame('referral', $limited[$s->id]['source']);
        $this->assertArrayNotHasKey($other->id, $limited);
        $this->assertSame([], SafetyFlags::forStudents([]));
    }

    public function test_benign_and_auto_escalated_only_cases_are_not_flagged(): void
    {
        $s = Student::factory()->create();
        $this->referralFor($s, '[AUTO-ESCALATED from Behavioral Report #1] [Flagged: description names violence/a weapon/a threat] Disrupted class.');
        $this->referralFor(Student::factory()->create(), 'Frequent tardiness.');

        $this->assertSame([], SafetyFlags::forStudents());
    }

    // ── At-Risk page ─────────────────────────────────────────────────────

    public function test_the_at_risk_row_shows_a_safety_chip_with_the_reason(): void
    {
        $flagged = $this->stabber();
        $plain = Student::factory()->create(['first_name' => 'Plainone']);
        $this->assess($plain, 'moderate', 62);
        $this->referralFor($plain, 'Frequent tardiness.');

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent();

        $this->assertSame(1, substr_count($html, 'data-safety-chip'), 'only the flagged student carries the chip');
        $this->assertStringContainsString('mentions &quot;stab&quot;', $html);
    }

    public function test_the_safety_flag_filter_lists_only_flagged_students_whatever_their_level(): void
    {
        $flagged = $this->stabber();                    // Moderate
        $high = Student::factory()->create();
        $this->assess($high, 'high', 92);               // High but no safety words
        $this->referralFor($high, 'Repeated absences.');

        $ids = $this->actingAs($this->counselor())->get(route('admin.risk.index', ['attention' => 'safety']))
            ->viewData('assessments')->pluck('student_id')->all();

        $this->assertSame([$flagged->id], $ids);
    }

    public function test_the_at_risk_page_offers_the_safety_chip_with_its_count_first(): void
    {
        $this->stabber();

        $page = $this->actingAs($this->counselor())->get(route('admin.risk.index'));

        $this->assertSame(1, $page->viewData('attentionCounts')['safety']);
        $this->assertSame('safety', array_key_first($page->viewData('attentionFilters')));
        $this->assertStringContainsString('Safety flag', $page->getContent());
    }

    public function test_the_safety_filter_survives_search_and_export(): void
    {
        $flagged = $this->stabber();
        $csv = $this->actingAs($this->counselor())->get(route('admin.risk.export', ['attention' => 'safety']))->streamedContent();

        $this->assertStringContainsString('Stabby', $csv);
    }

    // ── Dashboard ────────────────────────────────────────────────────────

    public function test_the_safety_tile_leads_the_dashboard_card_and_matches_the_list_it_opens(): void
    {
        $this->stabber();
        $c = $this->counselor();

        $page = $this->actingAs($c)->get(route('counselor.dashboard'));
        $tiles = collect($page->viewData('attentionTiles'));

        $this->assertSame('safety', $tiles->first()['key']);
        $this->assertSame('red', $tiles->first()['tone']);
        $this->assertSame(1, $tiles->first()['count']);
        $this->assertSame(1, $this->actingAs($c)->get($tiles->first()['url'])->viewData('assessments')->total());
        $this->assertStringContainsString('data-attention="safety"', $page->getContent());
    }

    public function test_the_safety_tile_is_hidden_when_nobody_is_flagged(): void
    {
        $html = $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->getContent();

        $this->assertStringNotContainsString('data-attention="safety"', $html);
    }

    public function test_the_watchlist_includes_a_flagged_moderate_student_and_puts_them_first(): void
    {
        $flagged = $this->stabber();                                     // Moderate 60.8, flagged
        $top = Student::factory()->create(['first_name' => 'Topscore']);
        $this->assess($top, 'high', 96);                                 // higher score, no flag
        $this->referralFor($top, 'Repeated absences.');
        $moderate = Student::factory()->create();
        $this->assess($moderate, 'moderate', 59);                        // moderate, no flag: not on the watchlist
        $this->referralFor($moderate, 'Late to class.');

        $page = $this->actingAs($this->counselor())->get(route('counselor.dashboard'));
        $ids = $page->viewData('watchlistAssessments')->pluck('student_id')->all();

        $this->assertSame([$flagged->id, $top->id], $ids, 'flagged first, then the high-risk by score; the plain moderate is excluded');
        $this->assertSame(1, substr_count($page->getContent(), 'data-safety-chip'));
    }

    public function test_the_watchlist_keeps_at_most_five_with_flagged_students_never_pushed_out(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $s = Student::factory()->create();
            $this->assess($s, 'high', 90 + $i);
            $this->referralFor($s, 'Repeated absences.');
        }
        $flagged = $this->stabber();

        $ids = $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->viewData('watchlistAssessments')->pluck('student_id')->all();

        $this->assertCount(5, $ids);
        $this->assertSame($flagged->id, $ids[0]);
    }

    // ── Profile pages ────────────────────────────────────────────────────

    public function test_the_risk_profile_and_student_page_show_the_alert_with_a_link_to_the_case(): void
    {
        $s = $this->stabber();
        $referral = Referral::where('student_id', $s->id)->first();
        $c = $this->counselor();

        foreach ([route('admin.risk.show', $s->id), route('admin.students.show', $s->id)] as $url) {
            $html = $this->actingAs($c)->get($url)->getContent();
            $this->assertStringContainsString('data-safety-flag', $html);
            $this->assertStringContainsString(route('admin.referrals.show', $referral->id), $html);
        }
    }

    public function test_a_report_sourced_flag_links_to_the_report(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);
        $report = BehavioralReport::factory()->create(['student_id' => $s->id, 'status' => 'pending', 'description' => 'He had a knife in his bag.']);

        $html = $this->actingAs($this->counselor())->get(route('admin.students.show', $s->id))->getContent();

        $this->assertStringContainsString('data-safety-flag', $html);
        $this->assertStringContainsString(route('admin.behavioral-reports.show', $report->id), $html);
    }

    public function test_an_unflagged_student_shows_no_alert(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);
        $this->referralFor($s, 'Frequent tardiness.');
        $c = $this->counselor();

        $this->assertStringNotContainsString('data-safety-flag', $this->actingAs($c)->get(route('admin.risk.show', $s->id))->getContent());
        $this->assertStringNotContainsString('data-safety-flag', $this->actingAs($c)->get(route('admin.students.show', $s->id))->getContent());
    }
}
