<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * Regression tests from the At-Risk Students page audit: bulk "Assign
 * Counselor" (duplicates, silent no-ops, stale ids, wrong priority/reason,
 * non-counselor targets), list/detail disagreement, the history chart, sort
 * and search behaviour, "My Students" scope, and CSV export parity/safety.
 */
class AdminRiskPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function assess(Student $s, string $level = 'high', float $score = 90, $at = null): RiskAssessment
    {
        return RiskAssessment::create([
            'student_id' => $s->id, 'risk_score' => $score, 'risk_level' => $level,
            'assessed_at' => $at ?? now(),
        ]);
    }

    private function assign(User $actor, array $ids, ?int $counselorId)
    {
        return $this->actingAs($actor)->post(route('admin.risk.bulkAction'), [
            'assessment_ids' => $ids, 'action' => 'assign_counselor', 'assign_counselor_id' => $counselorId,
        ]);
    }

    // ── Bulk assign ──────────────────────────────────────────────────────

    public function test_bulk_assign_rejects_a_non_counselor_target(): void
    {
        $teacher = User::factory()->teacher()->create();
        $a = $this->assess(Student::factory()->create());

        $this->assign($this->counselor(), [$a->id], $teacher->id)->assertSessionHasErrors('assign_counselor_id');
        $this->assertSame(0, Referral::count());
    }

    public function test_bulk_assign_creates_a_linked_referral_and_notifies_the_counselor(): void
    {
        $c = $this->counselor();
        $target = $this->counselor();
        $s = Student::factory()->create();
        $a = $this->assess($s, 'high', 91.5);

        $this->assign($c, [$a->id], $target->id)->assertSessionHas('success');

        $r = Referral::where('student_id', $s->id)->sole();
        $this->assertSame($target->id, $r->counselor_id);
        $this->assertSame('high', $r->priority);
        $this->assertSame($a->id, $r->risk_assessment_id);
        $this->assertStringContainsString('High Risk', $r->reason);
        $this->assertSame(1, Notification::where('user_id', $target->id)->count());
    }

    public function test_low_and_moderate_students_get_their_own_priority_and_reason(): void
    {
        $c = $this->counselor();
        $low = Student::factory()->create();
        $mod = Student::factory()->create();
        $la = $this->assess($low, 'low', 12);
        $ma = $this->assess($mod, 'moderate', 55);

        $this->assign($c, [$la->id, $ma->id], $c->id);

        $lr = Referral::where('student_id', $low->id)->sole();
        $mr = Referral::where('student_id', $mod->id)->sole();
        $this->assertSame('low', $lr->priority);
        $this->assertStringContainsString('Low Risk', $lr->reason);
        $this->assertStringNotContainsString('High Risk', $lr->reason);
        $this->assertSame('moderate', $mr->priority);
        $this->assertStringContainsString('Moderate Risk', $mr->reason);
    }

    public function test_an_existing_open_referral_is_assigned_not_duplicated(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        $a = $this->assess($s);
        $existing = Referral::factory()->create(['student_id' => $s->id, 'status' => 'in_progress', 'counselor_id' => null, 'referral_type' => 'Misconduct']);

        $this->assign($c, [$a->id], $c->id)->assertSessionHas('success');

        $this->assertSame(1, Referral::where('student_id', $s->id)->count());
        $this->assertSame($c->id, $existing->fresh()->counselor_id);
    }

    public function test_reassigning_moves_the_open_referral_and_reports_it_honestly(): void
    {
        $c1 = $this->counselor();
        $c2 = $this->counselor();
        $s = Student::factory()->create();
        $a = $this->assess($s);

        $this->assign($c1, [$a->id], $c1->id);
        $response = $this->assign($c1, [$a->id], $c2->id);

        $this->assertSame(1, Referral::where('student_id', $s->id)->count());
        $this->assertSame($c2->id, Referral::where('student_id', $s->id)->first()->counselor_id);
        $response->assertSessionHas('success');
        $this->assertStringContainsString('reassigned', session('success'));
    }

    public function test_assigning_to_the_counselor_who_already_has_it_is_reported_as_no_change(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        $a = $this->assess($s);

        $this->assign($c, [$a->id], $c->id);
        $this->assign($c, [$a->id], $c->id)->assertSessionHas('error');

        $this->assertSame(1, Referral::where('student_id', $s->id)->count());
    }

    public function test_a_resolved_referral_does_not_block_a_new_alert(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        $a = $this->assess($s);
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'resolved']);

        $this->assign($c, [$a->id], $c->id);

        $this->assertSame(2, Referral::where('student_id', $s->id)->count());
    }

    public function test_a_stale_assessment_id_is_skipped(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        $old = $this->assess($s, 'high', 90, now()->subMonths(2));
        $this->assess($s, 'low', 10);

        $this->assign($c, [$old->id], $c->id)->assertSessionHas('error');

        $this->assertSame(0, Referral::where('student_id', $s->id)->count());
    }

    public function test_a_super_admin_can_bulk_assign_to_a_counselor(): void
    {
        $counselor = $this->counselor();
        $a = $this->assess(Student::factory()->create());
        $super = User::factory()->create(['role' => 'super_admin']);

        $this->assign($super, [$a->id], $counselor->id)->assertSessionHas('success');
        $this->assertSame(1, Referral::count());
    }

    // ── List / detail / chart ────────────────────────────────────────────

    public function test_the_detail_page_uses_the_same_latest_assessment_as_the_list(): void
    {
        $c = $this->counselor();
        $s = Student::factory()->create();
        $this->assess($s, 'low', 10, now());
        $this->assess($s, 'high', 90, now()->subDay()); // latest by id, earlier assessed_at

        $listed = $this->actingAs($c)->get(route('admin.risk.index'))->viewData('assessments')->first();
        $shown = $this->actingAs($c)->get(route('admin.risk.show', $s->id))->viewData('latestAssessment');

        $this->assertSame($listed->id, $shown->id);
    }

    public function test_the_history_chart_shows_the_most_recent_assessments(): void
    {
        $s = Student::factory()->create();
        for ($i = 12; $i >= 1; $i--) {
            $this->assess($s, 'high', 50, now()->subDays($i * 10));
        }

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringContainsString(now()->subDays(20)->format('M d'), $html, 'a recent assessment must be on the chart');
        $this->assertStringNotContainsString('>' . now()->subDays(120)->format('M d') . '<', $html, 'the oldest of 12 falls off a 10-bar chart');
    }

    public function test_equal_sort_values_order_stably_by_newest_assessment(): void
    {
        $c = $this->counselor();
        $first = $this->assess(Student::factory()->create(), 'high', 80);
        $second = $this->assess(Student::factory()->create(), 'high', 80);

        $ids = $this->actingAs($c)->get(route('admin.risk.index'))->viewData('assessments')->pluck('id')->all();

        $this->assertSame([$second->id, $first->id], $ids);
    }

    public function test_the_filter_form_carries_the_current_sort(): void
    {
        $this->assess(Student::factory()->create());

        $html = $this->actingAs($this->counselor())
            ->get(route('admin.risk.index', ['sort' => 'behavioral_reports_count', 'dir' => 'asc']))->getContent();

        $this->assertStringContainsString('name="sort" value="behavioral_reports_count"', $html);
        $this->assertStringContainsString('name="dir" value="asc"', $html);
    }

    public function test_search_finds_a_student_by_full_name_in_either_order(): void
    {
        $c = $this->counselor();
        $this->assess(Student::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']));
        $this->assess(Student::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Reyes']));

        foreach (['Maria Santos', 'Santos Maria', 'Santos, Maria', 'santos'] as $term) {
            $page = $this->actingAs($c)->get(route('admin.risk.index', ['search' => $term]));
            $this->assertSame(1, $page->viewData('assessments')->total(), "search '{$term}'");
        }
    }

    public function test_scope_mine_includes_students_with_no_referral_yet(): void
    {
        $c = $this->counselor();
        $other = $this->counselor();
        $noReferral = $this->assess(Student::factory()->create());
        $theirs = Student::factory()->create();
        $this->assess($theirs);
        Referral::factory()->create(['student_id' => $theirs->id, 'counselor_id' => $other->id]);

        $page = $this->actingAs($c)->get(route('admin.risk.index', ['scope' => 'mine']));

        $this->assertSame([$noReferral->id], $page->viewData('assessments')->pluck('id')->all());
        $page->assertViewHas('totalAssessed', 1);
    }

    public function test_teachers_cannot_open_the_risk_page(): void
    {
        $this->actingAs(User::factory()->teacher()->create())->get(route('admin.risk.index'))->assertForbidden();
    }

    public function test_the_page_links_to_an_export_that_carries_the_filters(): void
    {
        $this->assess(Student::factory()->create());

        $html = $this->actingAs($this->counselor())
            ->get(route('admin.risk.index', ['risk_level' => 'high', 'search' => 'x', 'sort' => 'risk_score', 'dir' => 'asc']))->getContent();

        $this->assertStringContainsString(e(route('admin.risk.export', ['risk_level' => 'high', 'search' => 'x', 'sort' => 'risk_score', 'dir' => 'asc'])), $html);
    }

    // ── Export ───────────────────────────────────────────────────────────

    public function test_export_honours_the_on_screen_sort(): void
    {
        $lo = Student::factory()->create(['first_name' => 'Lowone']);
        $hi = Student::factory()->create(['first_name' => 'Highone']);
        $this->assess($lo, 'low', 10);
        $this->assess($hi, 'high', 90);

        $csv = $this->actingAs($this->counselor())
            ->get(route('admin.risk.export', ['sort' => 'risk_score', 'dir' => 'asc']))->streamedContent();

        $this->assertLessThan(strpos($csv, 'Highone'), strpos($csv, 'Lowone'));
    }

    public function test_export_honours_search_level_and_scope(): void
    {
        $c = $this->counselor();
        $other = $this->counselor();
        $mine = Student::factory()->create(['first_name' => 'Mineonly']);
        $theirs = Student::factory()->create(['first_name' => 'Theirsonly']);
        $this->assess($mine, 'high');
        $this->assess($theirs, 'high');
        Referral::factory()->create(['student_id' => $theirs->id, 'counselor_id' => $other->id]);

        $scoped = $this->actingAs($c)->get(route('admin.risk.export', ['scope' => 'mine']))->streamedContent();
        $this->assertStringContainsString('Mineonly', $scoped);
        $this->assertStringNotContainsString('Theirsonly', $scoped);

        $searched = $this->actingAs($c)->get(route('admin.risk.export', ['search' => 'Theirsonly']))->streamedContent();
        $this->assertStringContainsString('Theirsonly', $searched);
        $this->assertStringNotContainsString('Mineonly', $searched);

        $level = $this->actingAs($c)->get(route('admin.risk.export', ['risk_level' => 'low']))->streamedContent();
        $this->assertStringNotContainsString('Mineonly', $level);
    }

    public function test_export_neutralises_spreadsheet_formulas(): void
    {
        // The name cell is "Last, First", so it's the last name that leads the cell.
        $this->assess(Student::factory()->create(['first_name' => 'Doe', 'last_name' => '=HYPERLINK("x")']));

        $csv = $this->actingAs($this->counselor())->get(route('admin.risk.export'))->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
    }

    public function test_export_selected_returns_only_the_chosen_rows(): void
    {
        $c = $this->counselor();
        $a = $this->assess(Student::factory()->create(['first_name' => 'Chosenone']));
        $this->assess(Student::factory()->create(['first_name' => 'Skippedone']));

        $csv = $this->actingAs($c)->post(route('admin.risk.bulkAction'), [
            'assessment_ids' => [$a->id], 'action' => 'export_selected',
        ])->streamedContent();

        $this->assertStringContainsString('Chosenone', $csv);
        $this->assertStringNotContainsString('Skippedone', $csv);
    }

    // ── Recommended seminar labelling ────────────────────────────────────

    private function assessWithTag(Student $s, string $tag, string $level = 'moderate'): RiskAssessment
    {
        return RiskAssessment::create([
            'student_id' => $s->id, 'risk_score' => 55, 'risk_level' => $level, 'assessed_at' => now(),
            'risk_factors' => ['reason' => 'Some incident text', 'recommended_seminar_tag' => $tag],
        ]);
    }

    public function test_the_column_is_labelled_recommended_seminar_not_factors(): void
    {
        $this->assessWithTag(Student::factory()->create(), 'anti_bullying');

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.index'))->getContent();

        $this->assertStringContainsString('Recommended Seminar', $html);
        $this->assertStringNotContainsString('>Factors<', $html);
    }

    public function test_the_values_formation_fallback_is_marked_as_a_default_suggestion(): void
    {
        $this->assessWithTag(Student::factory()->create(), 'values_formation');

        $c = $this->counselor();
        $list = $this->actingAs($c)->get(route('admin.risk.index'))->getContent();
        $this->assertStringContainsString('Values Formation', $list);
        $this->assertStringContainsString('>default<', $list);
    }

    public function test_a_specific_seminar_tag_is_not_marked_as_default(): void
    {
        $s = Student::factory()->create();
        $this->assessWithTag($s, 'attendance_intervention');

        $c = $this->counselor();
        $this->assertStringNotContainsString('>default<', $this->actingAs($c)->get(route('admin.risk.index'))->getContent());
        $this->assertStringNotContainsString('Default suggestion', $this->actingAs($c)->get(route('admin.risk.show', $s->id))->getContent());
    }

    public function test_the_detail_page_explains_the_values_formation_default(): void
    {
        $s = Student::factory()->create();
        $this->assessWithTag($s, 'values_formation');

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringContainsString('Recommended Seminar', $html);
        $this->assertStringContainsString('Default suggestion', $html);
    }

    // ── Refer form on the profile page ───────────────────────────────────

    public function test_the_refer_form_no_longer_offers_a_priority_the_server_would_ignore(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringNotContainsString('name="priority"', $html);
        $this->assertStringContainsString('Priority is set automatically', $html);
    }

    public function test_the_refer_form_warns_about_open_referrals_and_requires_confirmation(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);
        $open = Referral::factory()->create(['student_id' => $s->id, 'status' => 'pending']);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringContainsString('already has 1 open referral', $html);
        $this->assertStringContainsString('#' . $open->id, $html);
        $this->assertStringContainsString('name="confirm_duplicate"', $html);
    }

    public function test_the_refer_form_has_no_warning_when_nothing_is_open(): void
    {
        $s = Student::factory()->create();
        $this->assess($s);
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'resolved']);

        $html = $this->actingAs($this->counselor())->get(route('admin.risk.show', $s->id))->getContent();

        $this->assertStringNotContainsString('already has', $html);
        $this->assertStringNotContainsString('name="confirm_duplicate"', $html);
    }

    private function referPayload(Student $s, array $extra = []): array
    {
        return array_merge([
            'student_id' => $s->id, 'referral_type' => 'Misconduct', 'reason' => 'Follow-up concern.', 'guard_duplicate' => 1,
        ], $extra);
    }

    public function test_the_server_refuses_a_second_open_referral_without_confirmation(): void
    {
        $this->fakeMlEngine('moderate', 55);
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'in_progress']);

        $this->actingAs($this->counselor())->post(route('admin.referrals.store'), $this->referPayload($s))
            ->assertSessionHas('error');

        $this->assertSame(1, Referral::where('student_id', $s->id)->count());
    }

    public function test_a_confirmed_second_referral_is_filed(): void
    {
        $this->fakeMlEngine('moderate', 55);
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'pending']);

        $this->actingAs($this->counselor())->post(route('admin.referrals.store'), $this->referPayload($s, ['confirm_duplicate' => 1]))
            ->assertSessionHas('success');

        $this->assertSame(2, Referral::where('student_id', $s->id)->count());
    }

    public function test_the_first_referral_needs_no_confirmation(): void
    {
        $this->fakeMlEngine('moderate', 55);
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'resolved']);

        $this->actingAs($this->counselor())->post(route('admin.referrals.store'), $this->referPayload($s))
            ->assertSessionHas('success');

        $this->assertSame(2, Referral::where('student_id', $s->id)->count());
    }

    public function test_other_callers_of_referral_store_are_not_guarded(): void
    {
        $this->fakeMlEngine('moderate', 55);
        $s = Student::factory()->create();
        Referral::factory()->create(['student_id' => $s->id, 'status' => 'pending']);

        // The plain Referrals-page form never sends guard_duplicate.
        $payload = $this->referPayload($s);
        unset($payload['guard_duplicate']);
        $this->actingAs($this->counselor())->post(route('admin.referrals.store'), $payload)->assertSessionHas('success');

        $this->assertSame(2, Referral::where('student_id', $s->id)->count());
    }

    // ── Page assets ──────────────────────────────────────────────────────

    public function test_the_layouts_load_core_assets_locally_instead_of_from_slow_cdns(): void
    {
        foreach (['tailwind.play.js', 'alpine.min.js', 'chart.umd.js', 'tabler/tabler-icons.min.css', 'tabler/fonts/tabler-icons.woff2'] as $file) {
            $this->assertFileExists(public_path('vendor/' . $file));
        }

        $s = Student::factory()->create();
        $this->assess($s);

        foreach ([route('admin.risk.index'), route('admin.risk.show', $s->id)] as $url) {
            $html = $this->actingAs($this->counselor())->get($url)->getContent();
            $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
            $this->assertStringNotContainsString('cdnjs.cloudflare.com/ajax/libs/Chart.js', $html);
            $this->assertStringNotContainsString('alpinejs@3.x.x/dist/cdn.min.js', $html);
            $this->assertStringNotContainsString('icons-webfont@', $html);
            $this->assertStringContainsString('vendor/tailwind.play.js', $html);
        }
    }
}
