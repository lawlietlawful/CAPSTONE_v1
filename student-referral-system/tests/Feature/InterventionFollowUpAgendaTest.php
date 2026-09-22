<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A follow-up agenda grouping scheduled sessions into Overdue / Due Today /
 * Upcoming (30 days) — a lower-risk alternative to a full interactive
 * calendar widget, built directly on the overdue-tracking work already in
 * place, so a counselor can plan a week without scanning the whole log.
 */
class InterventionFollowUpAgendaTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_groups_interventions_into_the_correct_buckets(): void
    {
        $counselor = $this->counselor();

        $overdue = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Behavioral Contract',
            'follow_up_date' => now()->subWeek(),
        ]);
        $today = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Group Counseling',
            'follow_up_date' => now()->format('Y-m-d'),
        ]);
        $upcoming = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Academic Coaching',
            'follow_up_date' => now()->addDays(10),
        ]);
        $tooFarOut = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Psychological First Aid',
            'follow_up_date' => now()->addDays(45),
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.followups'));

        $overdueIds = $page->viewData('overdue')->pluck('id');
        $todayIds = $page->viewData('dueToday')->pluck('id');
        $upcomingIds = $page->viewData('upcoming')->pluck('id');

        $this->assertTrue($overdueIds->contains($overdue->id));
        $this->assertTrue($todayIds->contains($today->id));
        $this->assertTrue($upcomingIds->contains($upcoming->id));
        $this->assertFalse($upcomingIds->contains($tooFarOut->id));
    }

    public function test_excludes_resolved_interventions(): void
    {
        $counselor = $this->counselor();
        $resolved = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subWeek(),
            'outcome' => 'resolved',
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.followups'));

        $this->assertFalse($page->viewData('overdue')->pluck('id')->contains($resolved->id));
    }

    public function test_excludes_interventions_with_no_follow_up_date(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => null,
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.followups'));

        $this->assertSame(0, $page->viewData('overdue')->count());
        $this->assertSame(0, $page->viewData('dueToday')->count());
        $this->assertSame(0, $page->viewData('upcoming')->count());
    }

    public function test_scoped_to_the_current_counselor(): void
    {
        $me = $this->counselor();
        $someoneElse = $this->counselor();
        Intervention::factory()->create(['counselor_id' => $someoneElse->id, 'follow_up_date' => now()->subWeek()]);

        $page = $this->actingAs($me)->get(route('counselor.interventions.followups'));

        $this->assertSame(0, $page->viewData('overdue')->count());
    }

    public function test_page_renders_with_the_expected_section_headings(): void
    {
        $counselor = $this->counselor();

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.followups'));

        $page->assertOk();
        $page->assertSee('Overdue');
        $page->assertSee('Due Today');
        $page->assertSee('Upcoming');
    }

    // ── Overdue pagination ───────────────────────────────────────────────
    // Due Today and Upcoming are self-limiting (a single day, a fixed
    // 30-day window) — only Overdue can grow unbounded if a counselor falls
    // behind, so only it is paginated.

    public function test_overdue_is_paginated_at_four_per_page(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->count(6)->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subWeek(),
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.followups'));

        $overdue = $page->viewData('overdue');
        $this->assertSame(4, $overdue->count());
        $this->assertSame(6, $overdue->total());
        $page->assertSee('Overdue (6)');
    }

    public function test_overdue_second_page_shows_the_remaining_records(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->count(6)->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subWeek(),
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.followups', ['overdue_page' => 2]));

        $this->assertSame(2, $page->viewData('overdue')->count());
    }

    public function test_due_today_and_upcoming_are_not_paginated(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->count(6)->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->format('Y-m-d'),
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.followups'));

        $this->assertSame(6, $page->viewData('dueToday')->count());
    }
}
