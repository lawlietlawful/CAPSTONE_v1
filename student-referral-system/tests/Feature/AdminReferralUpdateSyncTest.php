<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Admin "Edit Referral" modal (Admin\ReferralController::update()) used
 * to save the status straight onto the model, bypassing
 * ReferralService::updateStatus() — so resolving a referral that way never
 * notified the filing teacher, never synced the linked behavioral report,
 * and never resolved its never-evaluated interventions, unlike every other
 * status-change path (dropdown, bulk action, Counselor pages).
 */
class AdminReferralUpdateSyncTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->counselor()->create();
    }

    private function editPayload(Referral $referral, string $status, array $extra = []): array
    {
        return array_merge([
            'referral_type' => $referral->referral_type,
            'reason'        => 'Updated reason.',
            'priority'      => 'high',
            'status'        => $status,
        ], $extra);
    }

    public function test_resolving_via_the_edit_modal_syncs_the_linked_behavioral_report(): void
    {
        $report = BehavioralReport::factory()->create(['status' => 'pending']);
        $referral = Referral::factory()->create([
            'behavioral_report_id' => $report->id,
            'student_id'           => $report->student_id,
            'status'               => 'in_progress',
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.referrals.update', $referral->id), $this->editPayload($referral, 'resolved'))
            ->assertRedirect();

        $this->assertSame('resolved', $report->fresh()->status);
    }

    public function test_resolving_via_the_edit_modal_resolves_never_evaluated_interventions(): void
    {
        $referral = Referral::factory()->create(['status' => 'in_progress']);
        $unevaluated = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => null]);
        $assessed = Intervention::factory()->create(['referral_id' => $referral->id, 'outcome' => 'improving']);

        $this->actingAs($this->admin())
            ->put(route('admin.referrals.update', $referral->id), $this->editPayload($referral, 'resolved'));

        $this->assertSame('resolved', $unevaluated->fresh()->outcome);
        $this->assertSame('improving', $assessed->fresh()->outcome, 'a real historical outcome must not be overwritten');
    }

    public function test_a_status_change_via_the_edit_modal_notifies_the_filing_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $referral = Referral::factory()->create(['status' => 'pending', 'referred_by' => $teacher->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.referrals.update', $referral->id), $this->editPayload($referral, 'in_progress'));

        $this->assertDatabaseHas('notifications', ['user_id' => $teacher->id, 'type' => 'referral_status']);
    }

    public function test_saving_the_edit_modal_without_changing_status_does_not_notify(): void
    {
        $teacher = User::factory()->teacher()->create();
        $referral = Referral::factory()->create(['status' => 'pending', 'referred_by' => $teacher->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.referrals.update', $referral->id), $this->editPayload($referral, 'pending'));

        $this->assertSame(0, Notification::where('user_id', $teacher->id)->count());
    }

    public function test_the_other_edit_modal_fields_and_existing_counselor_notes_are_still_saved(): void
    {
        $referral = Referral::factory()->create([
            'status' => 'pending', 'priority' => 'low', 'counselor_notes' => 'Keep these notes.',
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.referrals.update', $referral->id), $this->editPayload($referral, 'in_progress', ['reason' => 'New reason text.']));

        $fresh = $referral->fresh();
        $this->assertSame('high', $fresh->priority);
        $this->assertSame('New reason text.', $fresh->reason);
        $this->assertSame('in_progress', $fresh->status);
        $this->assertSame('Keep these notes.', $fresh->counselor_notes, 'counselor_notes must not be wiped by an edit');
    }

    public function test_reopening_via_the_edit_modal_clears_resolved_at(): void
    {
        $referral = Referral::factory()->create(['status' => 'resolved', 'resolved_at' => now()->subDay()]);

        $this->actingAs($this->admin())
            ->put(route('admin.referrals.update', $referral->id), $this->editPayload($referral, 'in_progress'));

        $this->assertNull($referral->fresh()->resolved_at);
    }
}
