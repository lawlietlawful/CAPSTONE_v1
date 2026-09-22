<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The index page's "Export Selected" bulk action exports only the checked
 * rows, overriding the normal search/outcome/etc. filters entirely — but
 * still scoped to the current counselor's own records, so a tampered
 * ids[] parameter can't be used to pull someone else's intervention data.
 */
class InterventionBulkExportTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_exports_only_the_selected_ids(): void
    {
        $counselor = $this->counselor();
        $selected = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Academic Coaching',
        ]);
        $notSelected = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Group Counseling',
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.interventions.export', ['ids' => [$selected->id]]));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Academic Coaching', $content);
        $this->assertStringNotContainsString('Group Counseling', $content);
    }

    public function test_cannot_export_another_counselors_intervention_via_a_tampered_ids_param(): void
    {
        $counselor = $this->counselor();
        $someoneElsesIntervention = Intervention::factory()->create([
            'counselor_id' => $this->counselor()->id,
            'intervention_type' => 'Psychological First Aid',
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.interventions.export', ['ids' => [$someoneElsesIntervention->id]]));

        $content = $response->streamedContent();
        $this->assertStringNotContainsString('Psychological First Aid', $content);
    }

    public function test_ids_selection_overrides_normal_filters(): void
    {
        $counselor = $this->counselor();
        $selected = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'outcome' => 'worsening',
            'intervention_type' => 'Behavioral Contract',
        ]);

        // Even with an "outcome=improving" filter present, an explicit ids[]
        // selection should still return the selected row regardless.
        $response = $this->actingAs($counselor)->get(route('counselor.interventions.export', [
            'ids' => [$selected->id],
            'outcome' => 'improving',
        ]));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Behavioral Contract', $content);
    }
}
