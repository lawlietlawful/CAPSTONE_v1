<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resolving a referral from its own page (not through an intervention's own
 * "Resolved" outcome) used to leave any never-evaluated intervention stuck
 * showing "Not yet evaluated" forever next to a "Resolved" referral.
 * ReferralService::updateStatus() now closes that out — but only for
 * interventions with no outcome recorded; one with a real historical
 * assessment (improving/no_change/worsening) is left untouched, since
 * overwriting it would falsify what actually happened at that session.
 */
class ReferralInterventionOutcomeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_resolving_the_referral_resolves_a_never_evaluated_intervention(): void
    {
        $referral = Referral::factory()->create(['status' => 'in_progress']);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => null]);

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame('resolved', $intervention->fresh()->outcome);
    }

    public function test_resolving_the_referral_does_not_overwrite_an_already_assessed_intervention(): void
    {
        $referral = Referral::factory()->create(['status' => 'in_progress']);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => 'improving']);

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame('improving', $intervention->fresh()->outcome);
    }

    public function test_cancelling_the_referral_does_not_touch_intervention_outcomes(): void
    {
        $referral = Referral::factory()->create(['status' => 'in_progress']);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => null]);

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertNull($intervention->fresh()->outcome);
    }

    public function test_only_never_evaluated_interventions_are_synced_among_several(): void
    {
        $referral = Referral::factory()->create(['status' => 'in_progress']);
        $unevaluated = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => null]);
        $worsening = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => 'worsening']);
        $alreadyResolved = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => 'resolved']);

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame('resolved', $unevaluated->fresh()->outcome);
        $this->assertSame('worsening', $worsening->fresh()->outcome);
        $this->assertSame('resolved', $alreadyResolved->fresh()->outcome);
    }

    public function test_no_op_resubmit_of_the_same_status_does_not_trigger_a_sync(): void
    {
        $referral = Referral::factory()->create(['status' => 'resolved', 'resolved_at' => now()]);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => null]);

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'resolved'])
            ->assertRedirect();

        // Not a bug — a resubmit isn't a fresh "case just closed" moment, so
        // it doesn't retroactively reach into an intervention added since.
        $this->assertNull($intervention->fresh()->outcome);
    }
}
