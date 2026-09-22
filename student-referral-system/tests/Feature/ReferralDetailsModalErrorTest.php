<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Log Parent Contact" and "Log Intervention" popups on the Referral
 * Details page used to close silently on a server-side validation failure —
 * no error text, no reopened modal, the typed content just gone. Each
 * modal's visibility is now driven by which fields the current $errors bag
 * actually has, so a failure on one form can't also blank out or wrongly
 * reopen the other.
 */
class ReferralDetailsModalErrorTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_failed_intervention_submission_reopens_the_intervention_modal(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create();

        $page = $this->actingAs($counselor)
            ->from(route('counselor.referrals.show', $referral->id))
            ->followingRedirects()
            ->post(route('counselor.interventions.store'), [
                'referral_id' => $referral->id,
                // intervention_type and description deliberately omitted.
                'intervention_date' => now()->format('Y-m-d'),
            ]);

        $page->assertSee("activeModal: 'intervention'", false);
    }

    public function test_page_with_no_errors_does_not_auto_open_any_modal(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create();

        $page = $this->actingAs($counselor)->get(route('counselor.referrals.show', $referral->id));

        $page->assertSee('activeModal: null', false);
        $page->assertDontSee("activeModal: 'intervention'", false);
    }

    public function test_failed_parent_contact_submission_reopens_that_modal_not_the_intervention_one(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create();

        $page = $this->actingAs($counselor)
            ->from(route('counselor.referrals.show', $referral->id))
            ->followingRedirects()
            ->post(route('counselor.referrals.logParentContact', $referral->id), [
                'contact_method' => 'call',
                // summary deliberately omitted.
            ]);

        // The parent-contact modal's div loses its "hidden" class...
        $page->assertDontSee('id="parent-contact-modal" class="fixed inset-0 z-50 hidden"', false);
        // ...while the unrelated intervention modal must not be forced open.
        $page->assertSee('activeModal: null', false);
    }

    public function test_invalid_intervention_submission_does_not_create_a_record(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create();

        $response = $this->actingAs($counselor)->post(route('counselor.interventions.store'), [
            'referral_id' => $referral->id,
            'intervention_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['intervention_type', 'description']);
        $this->assertSame(0, $referral->interventions()->count());
    }

    // ── Admin's index page: Edit modal vs. Create modal ─────────────────────
    // Admin's referrals page has both a "New Referral" modal and a per-row
    // "Edit Referral" modal on the same page, sharing one Alpine
    // activeModal/activeId pair. A failed Edit submission must reopen THAT
    // referral's Edit modal, not the unrelated Create one.

    public function test_failed_edit_submission_reopens_the_edit_modal_not_create(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $referral = Referral::factory()->create(['referral_type' => 'Tardiness']);

        $page = $this->actingAs($admin)
            ->from(route('admin.referrals.index'))
            ->followingRedirects()
            ->put(route('admin.referrals.update', $referral->id), [
                'edit_referral_id' => $referral->id,
                'referral_type' => 'Other',
                // referral_type_other deliberately omitted.
                'reason' => 'Updated reason.',
                'priority' => 'low',
                'status' => 'pending',
            ]);

        $page->assertSee("activeModal: 'edit'", false);
        $page->assertSee("activeId: {$referral->id}", false);
        $page->assertDontSee("activeModal: 'create'", false);
    }

    public function test_failed_create_submission_on_admin_page_reopens_create_not_edit(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        // An existing referral just needs to be present so the page renders
        // its (unrelated) Edit modal alongside the Create one.
        Referral::factory()->create();

        $page = $this->actingAs($admin)
            ->from(route('admin.referrals.index'))
            ->followingRedirects()
            ->post(route('admin.referrals.store'), [
                'referral_type' => 'Other',
                'reason' => 'Missing student and specify field.',
            ]);

        $page->assertSee("activeModal: 'create'", false);
    }
}
