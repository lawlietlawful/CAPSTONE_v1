<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Log New Intervention" (index page) and "Edit Details" (Details page) used
 * to navigate to full standalone pages — both are now popup modals instead,
 * matching the pattern already used for "New Referral" on the Referral
 * index page. The standalone pages/routes stay in place for other entry
 * points (the empty-state link, the global quick-actions widget, edit's own
 * old page), only these two specific buttons changed.
 */
class InterventionModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response([], 200)]);
    }

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ── Log New Intervention modal (index page) ─────────────────────────

    public function test_index_page_offers_the_referral_options_the_modal_needs(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'pending']);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index'));

        $page->assertViewHas('referrals');
        $this->assertTrue($page->viewData('referrals')->pluck('id')->contains($referral->id));
    }

    public function test_submitting_the_create_modal_logs_an_intervention(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'pending']);

        $this->actingAs($counselor)->post(route('counselor.interventions.store'), [
            'referral_id'       => $referral->id,
            'intervention_type' => 'One-on-One Counseling',
            'intervention_date' => now()->format('Y-m-d'),
            'description'       => 'Logged via the index page modal.',
        ])->assertRedirect(route('counselor.interventions.index'));

        $this->assertDatabaseHas('interventions', [
            'referral_id' => $referral->id,
            'description' => 'Logged via the index page modal.',
        ]);
    }

    public function test_failed_create_submission_reopens_the_modal_on_the_index_page(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'pending']);

        $page = $this->actingAs($counselor)
            ->from(route('counselor.interventions.index'))
            ->followingRedirects()
            ->post(route('counselor.interventions.store'), [
                'referral_id' => $referral->id,
                // intervention_type and description deliberately omitted.
                'intervention_date' => now()->format('Y-m-d'),
            ]);

        $page->assertSee("activeModal: 'create'", false);
    }

    public function test_index_page_does_not_auto_open_the_modal_with_no_errors(): void
    {
        $counselor = $this->counselor();

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index'));

        $page->assertSee('activeModal: null', false);
    }

    // ── Edit Details modal (Details page) ───────────────────────────────

    public function test_details_page_shows_the_edit_modal_trigger_for_the_owner_only(): void
    {
        $owner = $this->counselor();
        $viewer = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id]);

        $ownerPage = $this->actingAs($owner)->get(route('counselor.interventions.show', $intervention->id));
        $ownerPage->assertSee('Edit Intervention Record');

        $viewerPage = $this->actingAs($viewer)->get(route('counselor.interventions.show', $intervention->id));
        $viewerPage->assertDontSee('Edit Intervention Record');
    }

    public function test_submitting_the_edit_modal_updates_the_record(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $counselor->id]);

        $this->actingAs($counselor)->put(route('counselor.interventions.update', $intervention->id), [
            'edit_intervention_marker' => 1,
            'intervention_type' => 'Academic Coaching',
            'intervention_date' => now()->format('Y-m-d'),
            'description' => 'Updated via the Details page modal.',
        ])->assertRedirect(route('counselor.interventions.show', $intervention->id));

        $this->assertSame('Updated via the Details page modal.', $intervention->fresh()->description);
    }

    public function test_failed_edit_submission_reopens_the_edit_modal(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)
            ->from(route('counselor.interventions.show', $intervention->id))
            ->followingRedirects()
            ->put(route('counselor.interventions.update', $intervention->id), [
                'edit_intervention_marker' => 1,
                // intervention_type, intervention_date, description all omitted.
            ]);

        $page->assertSee("activeModal: 'edit'", false);
    }

    public function test_a_failed_quick_update_does_not_open_the_edit_modal(): void
    {
        // quickUpdate() shares field names (outcome, follow_up_date) with
        // the full edit form — the hidden edit_intervention_marker is what
        // tells them apart, so a quickUpdate failure must NOT be mistaken
        // for an Edit modal failure.
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'intervention_date' => now()->format('Y-m-d'),
        ]);

        $page = $this->actingAs($counselor)
            ->from(route('counselor.interventions.show', $intervention->id))
            ->followingRedirects()
            ->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
                'outcome' => 'not-a-real-outcome',
            ]);

        $page->assertSee('activeModal: null', false);
    }

    public function test_a_non_owning_counselor_cannot_submit_the_edit_modal(): void
    {
        $owner = $this->counselor();
        $someoneElse = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id, 'description' => 'Original.']);

        $this->actingAs($someoneElse)->put(route('counselor.interventions.update', $intervention->id), [
            'edit_intervention_marker' => 1,
            'intervention_type' => 'Academic Coaching',
            'intervention_date' => now()->format('Y-m-d'),
            'description' => 'Tampered.',
        ])->assertForbidden();

        $this->assertSame('Original.', $intervention->fresh()->description);
    }
}
