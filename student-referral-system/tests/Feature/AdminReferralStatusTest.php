<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Admin\ReferralController::updateStatus() and bulkAction() used to have
 * their own copy of the resolved_at/notification logic, separate from
 * Counselor\ReferralController's — and it had drifted: it unconditionally
 * overwrote resolved_at on every "resolved" submission (even a no-op
 * resubmit), never cleared it on reopen, and never notified the filing
 * teacher. Counselor's copy had already been fixed for all three; Admin's
 * hadn't. Both now share one method (ReferralService::updateStatus) so they
 * can't independently drift apart again.
 */
class AdminReferralStatusTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    // ── Single updateStatus() ────────────────────────────────────────────

    public function test_resolving_sets_resolved_at(): void
    {
        $referral = Referral::factory()->create(['status' => 'pending']);

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.referrals.updateStatus', $referral->id), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertNotNull($referral->fresh()->resolved_at);
    }

    public function test_reopening_a_resolved_referral_clears_resolved_at(): void
    {
        $referral = Referral::factory()->create(['status' => 'resolved', 'resolved_at' => now()->subDays(5)]);

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.referrals.updateStatus', $referral->id), ['status' => 'in_progress'])
            ->assertRedirect();

        $this->assertNull($referral->fresh()->resolved_at);
    }

    public function test_resubmitting_the_same_resolved_status_does_not_overwrite_the_original_timestamp(): void
    {
        $originalResolvedAt = now()->subDays(10);
        $referral = Referral::factory()->create(['status' => 'resolved', 'resolved_at' => $originalResolvedAt]);

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.referrals.updateStatus', $referral->id), [
                'status' => 'resolved',
                'counselor_notes' => 'Just adding a note, not actually changing anything.',
            ])
            ->assertRedirect();

        $this->assertEquals(
            $originalResolvedAt->format('Y-m-d H:i:s'),
            $referral->fresh()->resolved_at->format('Y-m-d H:i:s')
        );
    }

    public function test_a_status_change_notifies_the_teacher_who_filed_it(): void
    {
        $teacher = User::factory()->teacher()->create();
        $referral = Referral::factory()->create(['referred_by' => $teacher->id, 'status' => 'pending']);

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.referrals.updateStatus', $referral->id), ['status' => 'in_progress']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $teacher->id,
            'type' => 'referral_status',
        ]);
    }

    public function test_no_notification_fires_when_the_status_does_not_actually_change(): void
    {
        $teacher = User::factory()->teacher()->create();
        $referral = Referral::factory()->create(['referred_by' => $teacher->id, 'status' => 'pending']);

        $this->actingAs($this->superAdmin())
            ->patch(route('admin.referrals.updateStatus', $referral->id), ['status' => 'pending']);

        $this->assertDatabaseCount('notifications', 0);
    }

    // ── Bulk status actions ──────────────────────────────────────────────

    public function test_bulk_resolve_sets_resolved_at_for_each_referral(): void
    {
        $referrals = Referral::factory()->count(3)->create(['status' => 'pending']);

        $this->actingAs($this->superAdmin())->post(route('admin.referrals.bulkAction'), [
            'referral_ids' => $referrals->pluck('id')->all(),
            'action' => 'resolved',
        ])->assertRedirect();

        foreach ($referrals as $referral) {
            $this->assertNotNull($referral->fresh()->resolved_at);
        }
    }

    public function test_bulk_reopen_clears_resolved_at_for_each_referral(): void
    {
        $referrals = Referral::factory()->count(3)->create(['status' => 'resolved', 'resolved_at' => now()->subWeek()]);

        $this->actingAs($this->superAdmin())->post(route('admin.referrals.bulkAction'), [
            'referral_ids' => $referrals->pluck('id')->all(),
            'action' => 'in_progress',
        ])->assertRedirect();

        foreach ($referrals as $referral) {
            $this->assertNull($referral->fresh()->resolved_at);
        }
    }

    public function test_bulk_status_change_notifies_each_filing_teacher(): void
    {
        $teacherA = User::factory()->teacher()->create();
        $teacherB = User::factory()->teacher()->create();
        $referralA = Referral::factory()->create(['referred_by' => $teacherA->id, 'status' => 'pending']);
        $referralB = Referral::factory()->create(['referred_by' => $teacherB->id, 'status' => 'pending']);

        $this->actingAs($this->superAdmin())->post(route('admin.referrals.bulkAction'), [
            'referral_ids' => [$referralA->id, $referralB->id],
            'action' => 'resolved',
        ]);

        $this->assertDatabaseHas('notifications', ['user_id' => $teacherA->id, 'type' => 'referral_status']);
        $this->assertDatabaseHas('notifications', ['user_id' => $teacherB->id, 'type' => 'referral_status']);
    }

    // ── Orphaned edit route removed ──────────────────────────────────────

    public function test_the_broken_edit_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('admin.referrals.edit'));
    }

    public function test_the_inline_update_action_still_works(): void
    {
        $referral = Referral::factory()->create(['status' => 'pending', 'priority' => 'low']);

        $response = $this->actingAs($this->superAdmin())->put(route('admin.referrals.update', $referral->id), [
            'referral_type' => $referral->referral_type,
            'reason' => 'Updated reason text.',
            'priority' => 'high',
            'status' => 'in_progress',
        ]);

        $response->assertRedirect(route('admin.referrals.index'));
        $this->assertSame('high', $referral->fresh()->priority);
    }
}
