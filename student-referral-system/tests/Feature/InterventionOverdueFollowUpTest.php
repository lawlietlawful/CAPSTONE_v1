<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nothing in the app previously flagged a scheduled follow-up that had
 * already passed — a counselor had no way to tell which ones needed
 * attention without checking every record by hand. Adds an is_follow_up_overdue
 * accessor and an overdueFollowUp() scope, surfaced as a 4th stat tile
 * (clickable, like the Referral pages' summary cards) and a visual flag on
 * both the index table and the Details page.
 */
class InterventionOverdueFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_a_past_follow_up_date_with_no_resolution_is_overdue(): void
    {
        $intervention = Intervention::factory()->create([
            'follow_up_date' => now()->subDays(3),
            'outcome' => null,
        ]);

        $this->assertTrue($intervention->is_follow_up_overdue);
    }

    public function test_a_follow_up_due_today_is_not_yet_overdue(): void
    {
        $intervention = Intervention::factory()->create([
            'follow_up_date' => now()->startOfDay(),
            'outcome' => null,
        ]);

        $this->assertFalse($intervention->is_follow_up_overdue);
    }

    public function test_a_future_follow_up_is_not_overdue(): void
    {
        $intervention = Intervention::factory()->create([
            'follow_up_date' => now()->addDays(5),
        ]);

        $this->assertFalse($intervention->is_follow_up_overdue);
    }

    public function test_no_follow_up_date_is_not_overdue(): void
    {
        $intervention = Intervention::factory()->create(['follow_up_date' => null]);

        $this->assertFalse($intervention->is_follow_up_overdue);
    }

    public function test_a_past_follow_up_date_already_resolved_is_not_overdue(): void
    {
        $intervention = Intervention::factory()->create([
            'follow_up_date' => now()->subDays(3),
            'outcome' => 'resolved',
        ]);

        $this->assertFalse($intervention->is_follow_up_overdue);
    }

    public function test_overdue_stat_and_filter_on_the_index_page(): void
    {
        $counselor = $this->counselor();
        $overdue = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subWeek(),
        ]);
        $onTrack = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->addWeek(),
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index'));
        $page->assertSee('Overdue Follow-ups');
        $page->assertViewHas('overdueCount', 1);

        $filtered = $this->actingAs($counselor)->get(route('counselor.interventions.index', ['overdue' => 1]));
        $ids = $filtered->viewData('interventions')->pluck('id');
        $this->assertTrue($ids->contains($overdue->id));
        $this->assertFalse($ids->contains($onTrack->id));
    }

    public function test_overdue_stat_is_scoped_to_the_current_counselor(): void
    {
        $me = $this->counselor();
        $someoneElse = $this->counselor();
        Intervention::factory()->create(['counselor_id' => $someoneElse->id, 'follow_up_date' => now()->subWeek()]);

        $page = $this->actingAs($me)->get(route('counselor.interventions.index'));

        $page->assertViewHas('overdueCount', 0);
    }
}
