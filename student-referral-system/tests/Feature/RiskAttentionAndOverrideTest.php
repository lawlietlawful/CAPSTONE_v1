<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\RiskAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * The "Needs attention" views on the At-Risk list (rising / stale / no open
 * referral / unassigned), the counselor's manual review & override, and the
 * richer profile page (concern type, referral status, interventions, log).
 */
class RiskAttentionAndOverrideTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function assess(Student $s, string $level = 'high', float $score = 90, $at = null, array $factors = []): RiskAssessment
    {
        return RiskAssessment::create([
            'student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level,
            'assessed_at' => $at ?? now(), 'risk_factors' => $factors ?: null,
        ]);
    }

    private function ids(User $viewer, array $query): array
    {
        return $this->actingAs($viewer)->get(route('admin.risk.index', $query))
            ->viewData('assessments')->pluck('student_id')->all();
    }

    // ── Needs attention filters ──────────────────────────────────────────

    public function test_rising_lists_students_whose_score_went_up_by_five_or_more(): void
    {
        $up = Student::factory()->create();
        $this->assess($up, 'moderate', 45, now()->subDays(5));
        $this->assess($up, 'high', 70);

        $small = Student::factory()->create();
        $this->assess($small, 'moderate', 45, now()->subDays(5));
        $this->assess($small, 'moderate', 48);

        $down = Student::factory()->create();
        $this->assess($down, 'high', 80, now()->subDays(5));
        $this->assess($down, 'moderate', 50);

        $single = Student::factory()->create();
        $this->assess($single, 'high', 90);

        $this->assertSame([$up->id], $this->ids($this->counselor(), ['attention' => 'rising']));
    }

    public function test_stale_lists_old_high_and_moderate_assessments_only(): void
    {
        $oldHigh = Student::factory()->create();
        $this->assess($oldHigh, 'high', 90, now()->subDays(40));
        $oldModerate = Student::factory()->create();
        $this->assess($oldModerate, 'moderate', 55, now()->subDays(31));
        $recent = Student::factory()->create();
        $this->assess($recent, 'high', 90, now()->subDays(10));
        $oldLow = Student::factory()->create();
        $this->assess($oldLow, 'low', 15, now()->subDays(90));

        $found = $this->ids($this->counselor(), ['attention' => 'stale']);

        $this->assertEqualsCanonicalizing([$oldHigh->id, $oldModerate->id], $found);
    }

    public function test_no_referral_lists_high_and_moderate_students_with_nothing_open(): void
    {
        $bare = Student::factory()->create();
        $this->assess($bare, 'high');

        $onlyResolved = Student::factory()->create();
        $this->assess($onlyResolved, 'moderate', 55);
        Referral::factory()->create(['student_id' => $onlyResolved->id, 'status' => 'resolved']);

        $covered = Student::factory()->create();
        $this->assess($covered, 'high');
        Referral::factory()->create(['student_id' => $covered->id, 'status' => 'pending']);

        $lowBare = Student::factory()->create();
        $this->assess($lowBare, 'low', 15);

        $found = $this->ids($this->counselor(), ['attention' => 'no_referral']);

        $this->assertEqualsCanonicalizing([$bare->id, $onlyResolved->id], $found);
    }

    public function test_unassigned_lists_students_whose_open_referral_has_no_counselor(): void
    {
        $counselor = $this->counselor();

        $waiting = Student::factory()->create();
        $this->assess($waiting, 'high');
        Referral::factory()->create(['student_id' => $waiting->id, 'status' => 'pending', 'counselor_id' => null]);

        $owned = Student::factory()->create();
        $this->assess($owned, 'high');
        Referral::factory()->create(['student_id' => $owned->id, 'status' => 'pending', 'counselor_id' => $counselor->id]);

        $closedUnowned = Student::factory()->create();
        $this->assess($closedUnowned, 'high');
        Referral::factory()->create(['student_id' => $closedUnowned->id, 'status' => 'resolved', 'counselor_id' => null]);

        $this->assertSame([$waiting->id], $this->ids($counselor, ['attention' => 'unassigned']));
    }

    public function test_an_unknown_attention_value_filters_nothing(): void
    {
        $this->assess(Student::factory()->create());
        $this->assess(Student::factory()->create());

        $this->assertCount(2, $this->ids($this->counselor(), ['attention' => 'nonsense']));
    }

    public function test_the_page_shows_a_count_for_each_group_and_keeps_the_filter_on_the_form_and_export(): void
    {
        $bare = Student::factory()->create();
        $this->assess($bare, 'high');
        $counselor = $this->counselor();

        $page = $this->actingAs($counselor)->get(route('admin.risk.index', ['attention' => 'no_referral']));

        $this->assertSame(1, $page->viewData('attentionCounts')['no_referral']);
        $this->assertSame(0, $page->viewData('attentionCounts')['rising']);
        $html = $page->getContent();
        $this->assertStringContainsString('Needs attention', $html);
        $this->assertStringContainsString('name="attention" value="no_referral"', $html);
        $this->assertStringContainsString(e(route('admin.risk.export', ['attention' => 'no_referral'])), $html);
    }

    public function test_export_honours_the_attention_filter(): void
    {
        $bare = Student::factory()->create(['first_name' => 'Barestudent']);
        $this->assess($bare, 'high');
        $covered = Student::factory()->create(['first_name' => 'Coveredstudent']);
        $this->assess($covered, 'high');
        Referral::factory()->create(['student_id' => $covered->id, 'status' => 'pending']);

        $csv = $this->actingAs($this->counselor())->get(route('admin.risk.export', ['attention' => 'no_referral']))->streamedContent();

        $this->assertStringContainsString('Barestudent', $csv);
        $this->assertStringNotContainsString('Coveredstudent', $csv);
    }

    // ── Manual override ──────────────────────────────────────────────────

    private function override(User $by, Student $s, string $level, string $note = 'Met the student and parent; misunderstanding.')
    {
        return $this->actingAs($by)->post(route('admin.risk.override', $s->id), ['risk_level' => $level, 'note' => $note]);
    }

    public function test_an_override_adds_a_new_latest_assessment_and_keeps_the_history(): void
    {
        $by = $this->counselor();
        $s = Student::factory()->create();
        $this->assess($s, 'high', 94.1, null, ['reason' => 'Threatened a classmate.', 'recommended_seminar_tag' => 'anti_bullying']);

        $this->override($by, $s, 'low')->assertRedirect(route('admin.risk.show', $s->id))->assertSessionHas('success');

        $this->assertSame(2, RiskAssessment::where('student_id', $s->id)->count(), 'the AI assessment is kept, not edited');
        $latest = RiskAssessment::where('student_id', $s->id)->latest('id')->first();
        $this->assertSame('low', $latest->risk_level);
        $this->assertEquals(20.0, (float) $latest->risk_score, 'high score is out of the low band, so it lands on the band default');
        $this->assertSame('override', $latest->risk_factors['source']);
        $this->assertSame($by->id, $latest->risk_factors['override']['by_id']);
        $this->assertSame('high', $latest->risk_factors['override']['previous_level']);
        $this->assertSame('Met the student and parent; misunderstanding.', $latest->risk_factors['override']['note']);
        $this->assertSame('Threatened a classmate.', $latest->risk_factors['reason']);
        $this->assertSame('orientation', $latest->risk_factors['recommended_seminar_tag']);
    }

    public function test_an_override_within_the_same_band_keeps_the_score_and_seminar(): void
    {
        $s = Student::factory()->create();
        $this->assess($s, 'moderate', 55, null, ['recommended_seminar_tag' => 'academic_recovery']);

        $this->override($this->counselor(), $s, 'moderate', 'Reviewed: keeping moderate for now.');

        $latest = RiskAssessment::where('student_id', $s->id)->latest('id')->first();
        $this->assertEquals(55.0, (float) $latest->risk_score);
        $this->assertSame('academic_recovery', $latest->risk_factors['recommended_seminar_tag']);
    }

    public function test_an_override_validates_level_and_requires_a_real_reason(): void
    {
        $by = $this->counselor();
        $s = Student::factory()->create();
        $this->assess($s);

        $this->override($by, $s, 'low', 'short')->assertSessionHasErrors('note');
        $this->override($by, $s, 'low', '')->assertSessionHasErrors('note');
        $this->override($by, $s, 'critical')->assertSessionHasErrors('risk_level');

        $this->assertSame(1, RiskAssessment::where('student_id', $s->id)->count());
    }

    public function test_only_staff_can_override(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);

        $this->override(User::factory()->teacher()->create(), $s, 'low')->assertForbidden();
        $this->assertSame(1, RiskAssessment::where('student_id', $s->id)->count());
    }

    public function test_overriding_a_never_assessed_student_is_refused_cleanly(): void
    {
        $s = Student::factory()->create();

        $this->override($this->counselor(), $s, 'low')->assertRedirect(route('admin.risk.index'))->assertSessionHas('error');
        $this->assertSame(0, RiskAssessment::where('student_id', $s->id)->count());
    }

    public function test_an_override_supersedes_an_open_case_that_was_holding_the_score_up(): void
    {
        Http::fake([
            '*/predict' => Http::sequence()
                ->push(['risk_level' => 'high', 'risk_score' => 94.1, 'recommended_seminar_tag' => 'anti_bullying'], 200)
                ->push(['risk_level' => 'low', 'risk_score' => 30, 'recommended_seminar_tag' => 'orientation'], 200),
            '*' => Http::response([], 200),
        ]);
        $s = Student::factory()->create();
        $reporter = User::factory()->teacher()->create();
        $service = app(ReferralService::class);

        $service->create($reporter, ['student_id' => $s->id, 'referral_type' => 'Misconduct', 'reason' => 'Knife threat.']);
        $this->override($this->counselor(), $s, 'low');
        $service->create($reporter, ['student_id' => $s->id, 'referral_type' => 'Misconduct', 'reason' => 'Late once.']);

        $latest = RiskAssessment::where('student_id', $s->id)->latest('id')->first();
        $this->assertSame('low', $latest->risk_level, 'the earlier open case must not re-hold the score after a counselor reviewed it');
        $this->assertArrayNotHasKey('held_by_referral_id', $latest->risk_factors);
    }

    public function test_the_automatic_recheck_leaves_a_fresh_override_alone_until_it_expires(): void
    {
        $this->fakeMlEngine('low', 20, 'orientation');
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'resolved']);
        $this->assess($s, 'high', 90);
        $this->override($this->counselor(), $s, 'moderate', 'Reviewed with the family; keep an eye on it.');
        $service = app(RiskAssessmentService::class);
        $override = RiskAssessment::where('student_id', $s->id)->latest('id')->first();

        $this->assertTrue($service->hasActiveOverride($s->fresh()));
        $this->assertSame($override->id, $service->reassessOverTime($s->fresh())->id, 'no new assessment while the override is fresh');
        $this->assertSame(2, RiskAssessment::where('student_id', $s->id)->count());

        $this->travel(RiskAssessmentService::OVERRIDE_SHIELD_DAYS + 1)->days();

        $this->assertFalse($service->hasActiveOverride($s->fresh()));
        $rechecked = $service->reassessOverTime($s->fresh());
        $this->assertNotSame($override->id, $rechecked->id);
        $this->assertSame('recheck', $rechecked->risk_factors['source']);
    }

    public function test_the_scheduled_command_skips_a_student_with_an_active_override(): void
    {
        $this->fakeMlEngine('low', 20, 'orientation');
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'resolved']);
        $this->assess($s, 'high', 90);
        $this->override($this->counselor(), $s, 'high', 'Confirmed after meeting; stays high.');
        $this->travel(20)->days(); // stale enough for the command (14+ days), still inside the override window

        Artisan::call('students:reassess-risk');

        $this->assertStringContainsString('manual override still active', Artisan::output());
        $this->assertSame(2, RiskAssessment::where('student_id', $s->id)->count());
    }

    public function test_automatic_assessments_record_their_source(): void
    {
        $this->fakeMlEngine('moderate', 55, 'values_formation');
        $s = Student::factory()->create();

        app(ReferralService::class)->create(User::factory()->teacher()->create(), ['student_id' => $s->id, 'referral_type' => 'Misconduct', 'reason' => 'x']);
        $first = RiskAssessment::where('student_id', $s->id)->latest('id')->first();
        $this->assertSame('referral', $first->risk_factors['source']);

        $recheck = app(RiskAssessmentService::class)->reassessOverTime($s->fresh());
        $this->assertSame('recheck', $recheck->risk_factors['source']);
    }

    // ── Profile page ─────────────────────────────────────────────────────

    public function test_the_profile_shows_concern_type_not_a_bare_code(): void
    {
        $s = Student::factory()->create();
        RiskAssessment::create([
            'student_id' => $s->id, 'risk_score' => 90, 'risk_level' => 'high', 'assessed_at' => now(),
            'concern_type_encoded' => RiskAssessmentService::encodeConcernType('emotional'),
        ]);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringContainsString('Concern Type', $html);
        $this->assertStringContainsString('Emotional', $html);
        $this->assertStringNotContainsString('Concern Code', $html);
    }

    public function test_the_profile_summarises_recent_referrals_with_status_and_a_link_to_each(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);
        $r = Referral::factory()->create(['student_id' => $s->id, 'status' => 'in_progress']);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringContainsString('data-case-summary', $html);
        $this->assertStringContainsString('in progress', $html);
        $this->assertStringContainsString(route('admin.referrals.show', $r->id), $html);
    }

    public function test_the_profile_shows_the_three_newest_referrals_and_counts_the_rest(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);
        $ids = Referral::factory()->count(5)->create(['student_id' => $s->id, 'status' => 'resolved'])->pluck('id')->all();
        $c = $this->counselor();

        $html = $this->actingAs($c)->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringContainsString('+ 2 older in the full record', $html);
        $this->assertStringContainsString(route('admin.referrals.show', $ids[4]), $html, 'newest is listed');
        $this->assertStringNotContainsString(route('admin.referrals.show', $ids[0]), $html, 'oldest is left to the full record');

        $few = Student::factory()->create();
        $this->assess($few);
        Referral::factory()->count(2)->create(['student_id' => $few->id]);
        $this->assertStringNotContainsString('older in the full record', $this->actingAs($c)->get(route('admin.risk.show', $few->id))->getContent());
    }

    public function test_the_profile_is_a_summary_that_points_to_the_full_student_record(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);
        Referral::factory()->create(['student_id' => $s->id]);
        BehavioralReport::factory()->create(['student_id' => $s->id, 'incident_type' => 'Truancy', 'severity' => 'Medium']);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringContainsString('Open full student record', $html);
        $this->assertStringContainsString(route('admin.students.show', $s->id), $html);
        $this->assertStringContainsString('Latest incident', $html);
        $this->assertStringContainsString('Truancy', $html);
        $this->assertStringNotContainsString('Referral History', $html, 'the long history tables moved to the Student page');
        $this->assertStringNotContainsString('Behavioral Incidents', $html);
    }

    public function test_the_summary_counts_referrals_open_referrals_incidents_and_interventions(): void
    {
        $counselor = $this->counselor();
        $s = Student::factory()->create();
        $this->assess($s);
        $open = Referral::factory()->create(['student_id' => $s->id, 'status' => 'pending']);
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'resolved']);
        BehavioralReport::factory()->count(3)->create(['student_id' => $s->id]);
        Intervention::factory()->count(2)->create(['referral_id' => $open->id, 'counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)->get(route('admin.risk.show', $s->id));
        $html = preg_replace('/\s+/', ' ', strip_tags($page->getContent()));

        $this->assertMatchesRegularExpression('/2 Referrals 1 open 3 Incidents 2 Interventions/', $html);
        $this->assertSame(2, $page->viewData('interventionCount'));
    }

    public function test_the_profile_shows_the_most_recent_intervention_and_its_outcome(): void
    {
        $counselor = $this->counselor();
        $s = Student::factory()->create();
        $this->assess($s);
        $referral = Referral::factory()->create(['student_id' => $s->id]);
        Intervention::factory()->create([
            'referral_id' => $referral->id, 'counselor_id' => $counselor->id, 'intervention_date' => today()->subDays(10),
            'intervention_type' => 'Group Counseling', 'outcome' => 'worsening',
        ]);
        Intervention::factory()->create([
            'referral_id' => $referral->id, 'counselor_id' => $counselor->id, 'intervention_date' => today()->subDays(1),
            'intervention_type' => 'Behavioral Contract', 'outcome' => 'improving',
        ]);

        $html = $this->actingAs($counselor)->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringContainsString('Behavioral Contract', $html);
        $this->assertStringContainsString('improving', $html);
        $this->assertStringNotContainsString('Group Counseling', $html, 'only the latest session is summarised here');
    }

    public function test_an_intervention_with_no_outcome_says_not_yet_evaluated(): void
    {
        $counselor = $this->counselor();
        $s = Student::factory()->create();
        $this->assess($s);
        $referral = Referral::factory()->create(['student_id' => $s->id]);
        Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id, 'outcome' => null]);

        $this->assertStringContainsString('Not yet evaluated', $this->actingAs($counselor)->get(route('admin.risk.show', $s->id))->getContent());
    }

    public function test_the_profile_says_so_when_nothing_has_been_tried(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);

        $this->assertStringContainsString('No interventions recorded yet', $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent());
    }

    public function test_the_profile_only_counts_this_students_interventions(): void
    {
        $counselor = $this->counselor();
        $s = Student::factory()->create();
        $this->assess($s);
        Intervention::factory()->create(['intervention_type' => 'Academic Coaching']); // someone else's

        $this->assertStringNotContainsString('Academic Coaching', $this->actingAs($counselor)->get(route('admin.risk.show', $s->id))->getContent());
    }

    public function test_the_profile_and_list_show_who_made_a_manual_review(): void
    {
        $by = User::factory()->counselor()->create(['name' => 'Ma\'am Reviewer']);
        $s = Student::factory()->create();
        $this->assess($s, 'high', 94.1);
        $this->override($by, $s, 'low', 'Spoke with the family; resolved.');

        $detail = $this->actingAs($by)->get(route('admin.risk.show', $s->id))->getContent();
        $this->assertStringNotContainsString('Set manually by', $detail, 'no permanent banner; the log carries it');
        $this->assertStringContainsString('Spoke with the family; resolved.', $detail);
        $this->assertStringContainsString('Assessment Log', $detail);
        $this->assertStringContainsString('Manual review by', $detail);

        $list = $this->actingAs($by)->get(route('admin.risk.index'))->getContent();
        $this->assertStringContainsString('>manual<', $list);
    }
}
