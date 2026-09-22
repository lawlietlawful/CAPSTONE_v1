<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Intervention index could only be filtered by student and outcome —
 * no way to narrow down by intervention type or by when the session
 * happened. Adds both, mirroring the date_range preset pattern already used
 * on the Admin Referrals page.
 */
class InterventionIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_filters_by_intervention_type(): void
    {
        $counselor = $this->counselor();
        $match = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Academic Coaching',
        ]);
        $other = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Group Counseling',
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index', ['intervention_type' => 'Academic Coaching']));

        $ids = $page->viewData('interventions')->pluck('id');
        $this->assertTrue($ids->contains($match->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_filters_by_date_range_today(): void
    {
        $counselor = $this->counselor();
        $today = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_date' => now()->format('Y-m-d'),
        ]);
        $lastMonth = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_date' => now()->subMonth()->format('Y-m-d'),
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index', ['date_range' => 'today']));

        $ids = $page->viewData('interventions')->pluck('id');
        $this->assertTrue($ids->contains($today->id));
        $this->assertFalse($ids->contains($lastMonth->id));
    }

    public function test_filters_by_date_range_last_month(): void
    {
        $counselor = $this->counselor();
        $today = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_date' => now()->format('Y-m-d'),
        ]);
        $lastMonth = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_date' => now()->subMonth()->format('Y-m-d'),
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index', ['date_range' => 'last_month']));

        $ids = $page->viewData('interventions')->pluck('id');
        $this->assertTrue($ids->contains($lastMonth->id));
        $this->assertFalse($ids->contains($today->id));
    }

    public function test_type_and_date_range_filters_can_combine_with_outcome(): void
    {
        $counselor = $this->counselor();
        $match = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Academic Coaching',
            'intervention_date' => now()->format('Y-m-d'),
            'outcome' => 'improving',
        ]);
        $wrongOutcome = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Academic Coaching',
            'intervention_date' => now()->format('Y-m-d'),
            'outcome' => 'worsening',
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index', [
            'intervention_type' => 'Academic Coaching',
            'date_range' => 'today',
            'outcome' => 'improving',
        ]));

        $ids = $page->viewData('interventions')->pluck('id');
        $this->assertTrue($ids->contains($match->id));
        $this->assertFalse($ids->contains($wrongOutcome->id));
    }
}
