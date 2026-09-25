<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One name per thing. The sidebar said "Interventions" while the page said
 * "Intervention Logs" (and its button "Follow-up Agenda"), "Referrals" led to
 * "Referral Management", and the dashboard card said something else again.
 * The sidebar label, browser tab, page heading and links now all agree.
 */
class CounselorNamingTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    /** [sidebar label => url of the page it opens] */
    private function pages(): array
    {
        return [
            'Overview' => route('counselor.dashboard'),
            'At-Risk Students' => route('admin.risk.index'),
            'Behavioral Reports' => route('counselor.behavioral-reports.index'),
            'Referrals' => route('counselor.referrals.index'),
            'Interventions' => route('counselor.interventions.index'),
            'Students' => route('admin.students.index'),
        ];
    }

    private function heading(string $html): string
    {
        preg_match('#<h1[^>]*>\s*(.*?)\s*</h1>#s', $html, $m);

        return trim(strip_tags($m[1] ?? ''));
    }

    private function tab(string $html): string
    {
        preg_match('#<title>(.*?)</title>#s', $html, $m);

        return trim(html_entity_decode($m[1] ?? ''));
    }

    public function test_the_page_heading_matches_its_sidebar_label_everywhere(): void
    {
        $c = $this->counselor();

        foreach ($this->pages() as $label => $url) {
            $html = $this->actingAs($c)->get($url)->getContent();

            $this->assertSame($label, $this->heading($html), "heading of {$url}");
        }
    }

    public function test_the_browser_tab_starts_with_the_same_label(): void
    {
        $c = $this->counselor();

        foreach ($this->pages() as $label => $url) {
            $this->assertStringStartsWith($label, $this->tab($this->actingAs($c)->get($url)->getContent()), $url);
        }
    }

    public function test_the_sidebar_offers_exactly_those_labels(): void
    {
        $html = $this->actingAs($this->counselor())->get(route('counselor.dashboard'))->getContent();
        preg_match('#<nav.*?</nav>#s', $html, $m);
        $nav = preg_replace('/\s+/', ' ', strip_tags($m[0]));

        foreach (array_keys($this->pages()) as $label) {
            $this->assertStringContainsString($label, $nav);
        }
    }

    public function test_the_old_names_are_gone(): void
    {
        $c = $this->counselor();

        foreach ([route('counselor.referrals.index'), route('counselor.interventions.index'), route('admin.students.index'), route('counselor.dashboard')] as $url) {
            $html = $this->actingAs($c)->get($url)->getContent();
            foreach (['Referral Management', 'Intervention Logs', 'Follow-up Agenda', 'Students Management', 'Dashboard Overview', 'Early Warning System'] as $old) {
                $this->assertStringNotContainsString($old, $html, "'{$old}' on {$url}");
            }
        }
    }

    public function test_follow_ups_has_one_name_across_the_card_button_and_page(): void
    {
        $c = $this->counselor();

        $followUps = $this->actingAs($c)->get(route('counselor.interventions.followups'))->getContent();
        $this->assertSame('Follow-ups', $this->heading($followUps));
        $this->assertStringStartsWith('Follow-ups', $this->tab($followUps));

        $interventions = $this->actingAs($c)->get(route('counselor.interventions.index'))->getContent();
        $this->assertMatchesRegularExpression('#</i>\s*Follow-ups\s*</a>#', $interventions, 'the button that leads there uses the same word');

        $dashboard = $this->actingAs($c)->get(route('counselor.dashboard'))->getContent();
        $this->assertStringContainsString('Follow-ups Due', $dashboard);
    }

    public function test_the_admin_layout_pages_use_the_same_names_for_the_counselor(): void
    {
        $c = $this->counselor();

        $this->assertSame('Referrals', $this->heading($this->actingAs($c)->get(route('admin.referrals.index'))->getContent()));
    }
}
