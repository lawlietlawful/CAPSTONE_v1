<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edge cases found in a full audit of the Intervention flow: the "Last
 * month" filter on the 31st, the inline follow-up form's missing date rule,
 * a "Resolved" outcome silently reopening a Cancelled referral as Resolved,
 * and a success message that claimed an SMS went out when it hadn't.
 */
class InterventionEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function storePayload(Referral $referral, array $overrides = []): array
    {
        return array_merge([
            'referral_id'       => $referral->id,
            'intervention_type' => 'One-on-One Counseling',
            'intervention_date' => now()->toDateString(),
            'description'       => 'Session notes.',
        ], $overrides);
    }

    // ── Last-month filter ────────────────────────────────────────────────

    public function test_interventions_last_month_filter_is_correct_on_the_31st(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));
        $counselor = $this->counselor();
        Intervention::factory()->create(['counselor_id' => $counselor->id, 'intervention_date' => '2026-09-15']);
        Intervention::factory()->create(['counselor_id' => $counselor->id, 'intervention_date' => '2026-10-20']);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index', ['date_range' => 'last_month']));

        $this->assertSame(1, $page->viewData('interventions')->total(), 'only the September session belongs in "last month"');
    }

    // ── Inline follow-up form ────────────────────────────────────────────

    public function test_quick_update_rejects_a_follow_up_before_the_session_date(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $counselor->id, 'intervention_date' => '2026-09-20']);

        $this->actingAs($counselor)->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
            'outcome' => 'improving', 'follow_up_date' => '2026-09-01',
        ])->assertSessionHasErrors('follow_up_date');
    }

    public function test_quick_update_accepts_a_follow_up_on_or_after_the_session_date(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $counselor->id, 'intervention_date' => '2026-09-20']);

        $this->actingAs($counselor)->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
            'outcome' => 'improving', 'follow_up_date' => '2026-09-20',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-20', $intervention->fresh()->follow_up_date->toDateString());
    }

    public function test_quick_update_still_saves_an_outcome_on_a_legacy_row_with_an_out_of_order_follow_up(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create([
            'counselor_id' => $counselor->id, 'intervention_date' => '2026-09-20', 'follow_up_date' => '2026-09-01',
        ]);

        // The form resubmits the untouched date — that must not block saving the outcome.
        $this->actingAs($counselor)->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
            'outcome' => 'improving', 'follow_up_date' => '2026-09-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame('improving', $intervention->fresh()->outcome);
    }

    // ── Cancelled referrals ──────────────────────────────────────────────

    public function test_a_resolved_outcome_does_not_turn_a_cancelled_referral_into_resolved(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'cancelled']);

        $this->actingAs($counselor)->post(route('counselor.interventions.store'), $this->storePayload($referral, ['outcome' => 'resolved']));

        $this->assertSame('cancelled', $referral->fresh()->status);
        $this->assertNull($referral->fresh()->resolved_at);
    }

    public function test_quick_update_resolved_does_not_turn_a_cancelled_referral_into_resolved(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'cancelled']);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        $this->actingAs($counselor)->patch(route('counselor.interventions.quickUpdate', $intervention->id), ['outcome' => 'resolved']);

        $this->assertSame('cancelled', $referral->fresh()->status);
    }

    public function test_edit_resolved_does_not_turn_a_cancelled_referral_into_resolved(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'cancelled']);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        $this->actingAs($counselor)->put(route('counselor.interventions.update', $intervention->id), [
            'intervention_type' => $intervention->intervention_type,
            'intervention_date' => $intervention->intervention_date->toDateString(),
            'description'       => $intervention->description,
            'outcome'           => 'resolved',
        ]);

        $this->assertSame('cancelled', $referral->fresh()->status);
    }

    public function test_a_resolved_outcome_still_resolves_an_in_progress_referral(): void
    {
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'in_progress']);

        $this->actingAs($counselor)->post(route('counselor.interventions.store'), $this->storePayload($referral, ['outcome' => 'resolved']));

        $this->assertSame('resolved', $referral->fresh()->status);
    }

    // ── Success message honesty ──────────────────────────────────────────

    public function test_success_message_does_not_claim_an_sms_when_there_is_no_parent_number(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create(['parent_contact' => '']);
        $referral = Referral::factory()->create(['student_id' => $student->id, 'status' => 'pending']);

        $response = $this->actingAs($counselor)->post(route('counselor.interventions.store'), $this->storePayload($referral));

        $response->assertSessionHas('success', 'Intervention logged successfully.');
    }

    public function test_success_message_mentions_the_sms_when_one_was_sent(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create(['parent_contact' => '09171234567']);
        $referral = Referral::factory()->create(['student_id' => $student->id, 'status' => 'pending']);

        // No SMS API key is configured in tests, so SmsService simulates a successful send.
        $response = $this->actingAs($counselor)->post(route('counselor.interventions.store'), $this->storePayload($referral));

        $response->assertSessionHas('success', 'Intervention logged successfully. SMS notification sent to parent.');
    }
}
