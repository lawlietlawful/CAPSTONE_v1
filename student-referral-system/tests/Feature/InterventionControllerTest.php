<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers a batch of fixes to the Intervention flow:
 *
 * - store()/update() used to move a referral to in_progress/resolved via a
 *   raw Referral::update(), bypassing ReferralService::updateStatus() — the
 *   shared method that also notifies the filing teacher. That meant logging
 *   or editing an intervention (the most natural way a case actually gets
 *   resolved) never told the teacher anything happened, unlike using the
 *   separate "Update Referral" form.
 * - Any counselor can view any intervention (referrals are a shared
 *   caseload), but only the logging counselor can edit or delete one — the
 *   Details page used to always show Edit/Delete regardless, so a different
 *   counselor clicking them hit a dead-end 403.
 */
class InterventionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No test here needs a real ML call, and Intervention::store() sends
        // an SMS via SmsService — fake all outbound HTTP so nothing escapes.
        Http::fake(['*' => Http::response([], 200)]);
    }

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function teacher(): User
    {
        return User::factory()->create(['role' => 'teacher']);
    }

    // ── ReferralService integration ─────────────────────────────────────────

    public function test_logging_an_intervention_on_a_pending_referral_notifies_the_filing_teacher(): void
    {
        $teacher = $this->teacher();
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'pending', 'referred_by' => $teacher->id]);

        $this->actingAs($counselor)->post(route('counselor.interventions.store'), [
            'referral_id'       => $referral->id,
            'intervention_type' => 'One-on-One Counseling',
            'intervention_date' => now()->format('Y-m-d'),
            'description'       => 'Discussed the concern.',
        ])->assertRedirect(route('counselor.interventions.index'));

        $referral->refresh();
        $this->assertSame('in_progress', $referral->status);
        $this->assertSame($counselor->id, $referral->counselor_id);
        $this->assertDatabaseHas('notifications', [
            'user_id'      => $teacher->id,
            'reference_id' => $referral->id,
            'type'         => 'referral_status',
        ]);
    }

    public function test_logging_a_resolving_intervention_notifies_the_filing_teacher_and_sets_resolved_at(): void
    {
        $teacher = $this->teacher();
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $counselor->id, 'referred_by' => $teacher->id]);

        $this->actingAs($counselor)->post(route('counselor.interventions.store'), [
            'referral_id'       => $referral->id,
            'intervention_type' => 'One-on-One Counseling',
            'intervention_date' => now()->format('Y-m-d'),
            'description'       => 'Final session.',
            'outcome'           => 'resolved',
        ]);

        $referral->refresh();
        $this->assertSame('resolved', $referral->status);
        $this->assertNotNull($referral->resolved_at);
        $this->assertDatabaseHas('notifications', [
            'user_id'      => $teacher->id,
            'reference_id' => $referral->id,
            'type'         => 'referral_status',
        ]);
    }

    public function test_logging_an_intervention_does_not_wipe_existing_counselor_notes(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'pending', 'counselor_notes' => 'Prior context from intake.']);

        $this->actingAs($counselor)->post(route('counselor.interventions.store'), [
            'referral_id'       => $referral->id,
            'intervention_type' => 'One-on-One Counseling',
            'intervention_date' => now()->format('Y-m-d'),
            'description'       => 'Discussed the concern.',
        ]);

        $referral->refresh();
        $this->assertSame('Prior context from intake.', $referral->counselor_notes);
    }

    public function test_editing_an_intervention_to_resolved_notifies_the_filing_teacher(): void
    {
        $teacher = $this->teacher();
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $counselor->id, 'referred_by' => $teacher->id]);
        $intervention = Intervention::factory()->create([
            'referral_id'  => $referral->id,
            'counselor_id' => $counselor->id,
        ]);

        $this->actingAs($counselor)->put(route('counselor.interventions.update', $intervention->id), [
            'intervention_type' => $intervention->intervention_type,
            'intervention_date' => $intervention->intervention_date->format('Y-m-d'),
            'description'       => $intervention->description,
            'outcome'           => 'resolved',
        ]);

        $referral->refresh();
        $this->assertSame('resolved', $referral->status);
        $this->assertDatabaseHas('notifications', [
            'user_id'      => $teacher->id,
            'reference_id' => $referral->id,
            'type'         => 'referral_status',
        ]);
    }

    public function test_update_rejects_a_follow_up_date_before_the_intervention_date(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $counselor->id]);

        $response = $this->actingAs($counselor)->put(route('counselor.interventions.update', $intervention->id), [
            'intervention_type' => $intervention->intervention_type,
            'intervention_date' => '2026-06-10',
            'description'       => $intervention->description,
            'follow_up_date'    => '2026-06-01',
        ]);

        $response->assertSessionHasErrors('follow_up_date');
    }

    // ── View/edit/delete authorization ──────────────────────────────────────

    public function test_any_counselor_can_view_an_intervention_they_did_not_log(): void
    {
        $owner = $this->counselor();
        $viewer = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id]);

        $this->actingAs($viewer)
            ->get(route('counselor.interventions.show', $intervention->id))
            ->assertOk();
    }

    public function test_only_the_logging_counselor_can_edit_or_delete_it(): void
    {
        $owner = $this->counselor();
        $someoneElse = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id]);

        $this->actingAs($someoneElse)->get(route('counselor.interventions.edit', $intervention->id))->assertForbidden();
        $this->actingAs($someoneElse)->delete(route('counselor.interventions.destroy', $intervention->id))->assertForbidden();
        $this->assertDatabaseHas('interventions', ['id' => $intervention->id]);
    }

    public function test_details_page_hides_edit_and_delete_for_a_non_owning_counselor(): void
    {
        $owner = $this->counselor();
        $viewer = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id]);

        $page = $this->actingAs($viewer)->get(route('counselor.interventions.show', $intervention->id));

        $page->assertDontSee('Edit Details');
        $page->assertDontSee('Delete Log');
        $page->assertSee('only they can edit or delete', false);
    }

    public function test_details_page_shows_edit_and_delete_for_the_owning_counselor(): void
    {
        $owner = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id]);

        $page = $this->actingAs($owner)->get(route('counselor.interventions.show', $intervention->id));

        $page->assertSee('Edit Details');
        $page->assertSee('Delete Log');
    }

    // ── Empty-state copy ─────────────────────────────────────────────────
    // "No interventions logged yet" used to show even when a search/filter
    // just matched nothing, implying the log was empty when it wasn't.

    public function test_a_zero_result_search_shows_a_filter_specific_empty_state(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->create(['counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index', ['search' => 'zzzznomatch']));

        $page->assertSee('No interventions match your current search or filters.');
        $page->assertDontSee('No interventions logged yet.');
    }

    public function test_a_genuinely_empty_log_shows_the_original_empty_state(): void
    {
        $counselor = $this->counselor();

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index'));

        $page->assertSee('No interventions logged yet.');
    }
}
