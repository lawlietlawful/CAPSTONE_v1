<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Referrals and Behavioral Reports both have a print view and a CSV export;
 * Interventions had neither — a counselor needing a printed session record
 * for a parent meeting, or a CSV of their log for reporting, had no way to
 * get one.
 */
class InterventionPrintExportTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ── Print ────────────────────────────────────────────────────────────

    public function test_print_view_renders_for_the_owning_counselor(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $counselor->id]);

        $response = $this->actingAs($counselor)->get(route('counselor.interventions.print', $intervention->id));

        $response->assertOk();
        $response->assertSee('Intervention Session Report');
        $response->assertSee($intervention->intervention_type);
    }

    public function test_print_view_is_also_accessible_to_a_non_owning_counselor(): void
    {
        // Matches show()'s own "referrals are a shared caseload" reasoning —
        // any counselor can print the record, just not edit/delete it.
        $owner = $this->counselor();
        $viewer = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id]);

        $this->actingAs($viewer)
            ->get(route('counselor.interventions.print', $intervention->id))
            ->assertOk();
    }

    // ── Export ───────────────────────────────────────────────────────────

    public function test_export_returns_a_csv_of_the_counselors_own_interventions(): void
    {
        $counselor = $this->counselor();
        $mine = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Academic Coaching',
        ]);
        $someoneElses = Intervention::factory()->create([
            'counselor_id' => $this->counselor()->id,
            'intervention_type' => 'Group Counseling',
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.interventions.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Academic Coaching', $content);
        $this->assertStringNotContainsString('Group Counseling', $content);
    }

    public function test_export_respects_the_same_filters_as_the_index_page(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'outcome' => 'improving',
            'intervention_type' => 'Behavioral Contract',
        ]);
        Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'outcome' => 'worsening',
            'intervention_type' => 'Behavioral Contract',
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.interventions.export', ['outcome' => 'improving']));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Improving', $content);
        $this->assertStringNotContainsString('Worsening', $content);
    }

    public function test_export_includes_the_student_and_referral_details(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $referral = Referral::factory()->create(['student_id' => $student->id]);
        Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        $response = $this->actingAs($counselor)->get(route('counselor.interventions.export'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Santos, Maria', $content);
        $this->assertStringContainsString(str_pad($referral->id, 4, '0', STR_PAD_LEFT), $content);
    }
}
