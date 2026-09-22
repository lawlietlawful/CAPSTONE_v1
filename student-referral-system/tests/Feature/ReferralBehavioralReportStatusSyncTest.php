<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Once a behavioral report auto-escalates into a referral, the two records'
 * statuses used to live completely independently — resolving (or cancelling)
 * the referral never touched the originating report, which then sat
 * "Pending" in the reports queue forever even though the case was closed.
 * ReferralService::updateStatus() now syncs the linked report's status
 * whenever the referral's status actually changes.
 */
class ReferralBehavioralReportStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function escalatedReferral(string $referralStatus = 'pending', string $reportStatus = 'pending'): Referral
    {
        $report = BehavioralReport::factory()->create(['status' => $reportStatus]);

        return Referral::factory()->create([
            'behavioral_report_id' => $report->id,
            'student_id'           => $report->student_id,
            'status'               => $referralStatus,
        ]);
    }

    public function test_resolving_the_referral_marks_the_report_resolved(): void
    {
        $referral = $this->escalatedReferral(referralStatus: 'in_progress', reportStatus: 'reviewed');

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame('resolved', $referral->behavioralReport->fresh()->status);
    }

    public function test_cancelling_the_referral_also_marks_the_report_resolved(): void
    {
        $referral = $this->escalatedReferral(referralStatus: 'pending', reportStatus: 'pending');

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertSame('resolved', $referral->behavioralReport->fresh()->status);
    }

    public function test_moving_the_referral_in_progress_marks_the_report_reviewed(): void
    {
        $referral = $this->escalatedReferral(referralStatus: 'pending', reportStatus: 'pending');

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'in_progress'])
            ->assertRedirect();

        $this->assertSame('reviewed', $referral->behavioralReport->fresh()->status);
    }

    public function test_reopening_a_resolved_referral_moves_the_report_back_to_reviewed(): void
    {
        $referral = $this->escalatedReferral(referralStatus: 'resolved', reportStatus: 'resolved');

        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'in_progress'])
            ->assertRedirect();

        $this->assertSame('reviewed', $referral->behavioralReport->fresh()->status);
    }

    public function test_no_sync_happens_when_the_referral_status_does_not_actually_change(): void
    {
        $referral = $this->escalatedReferral(referralStatus: 'pending', reportStatus: 'resolved');

        // Report was manually left "resolved" even though the referral is
        // still "pending" — a resubmit of the SAME referral status must not
        // silently overwrite that back to "pending".
        $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'pending'])
            ->assertRedirect();

        $this->assertSame('resolved', $referral->behavioralReport->fresh()->status);
    }

    public function test_a_directly_filed_referral_with_no_behavioral_report_is_unaffected(): void
    {
        $referral = Referral::factory()->create(['status' => 'pending', 'behavioral_report_id' => null]);

        $response = $this->actingAs($this->counselor())
            ->patch(route('counselor.referrals.updateStatus', $referral->id), ['status' => 'resolved']);

        $response->assertRedirect();
        $this->assertNull($referral->fresh()->behavioral_report_id);
    }

    public function test_bulk_resolving_referrals_syncs_each_linked_report(): void
    {
        $referralA = $this->escalatedReferral(referralStatus: 'pending', reportStatus: 'pending');
        $referralB = $this->escalatedReferral(referralStatus: 'in_progress', reportStatus: 'reviewed');

        $this->actingAs($this->counselor())->post(route('admin.referrals.bulkAction'), [
            'referral_ids' => [$referralA->id, $referralB->id],
            'action'       => 'resolved',
        ])->assertRedirect();

        $this->assertSame('resolved', $referralA->behavioralReport->fresh()->status);
        $this->assertSame('resolved', $referralB->behavioralReport->fresh()->status);
    }
}
