<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Updating the outcome or follow-up date used to require the full Edit page
 * (which also demands re-submitting type/date/description). This inline
 * "Update Session" form on the Details page — mirroring Referral's own
 * always-visible "Update Referral" form — covers just those two fields.
 */
class InterventionQuickUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_owning_counselor_can_update_the_outcome(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $counselor->id, 'outcome' => null]);

        $this->actingAs($counselor)
            ->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
                'outcome' => 'improving',
            ])
            ->assertRedirect(route('counselor.interventions.show', $intervention->id));

        $this->assertSame('improving', $intervention->fresh()->outcome);
    }

    public function test_owning_counselor_can_reschedule_the_follow_up_date(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subWeek(),
        ]);

        $newDate = now()->addWeek()->format('Y-m-d');

        $this->actingAs($counselor)->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
            'follow_up_date' => $newDate,
        ]);

        $this->assertSame($newDate, $intervention->fresh()->follow_up_date->format('Y-m-d'));
    }

    public function test_rescheduling_the_follow_up_date_resets_the_reminder_gate(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subWeek(),
            'follow_up_notified_at' => now(),
        ]);

        $this->actingAs($counselor)->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
            'follow_up_date' => now()->addWeek()->format('Y-m-d'),
        ]);

        $this->assertNull($intervention->fresh()->follow_up_notified_at);
    }

    public function test_keeping_the_same_follow_up_date_does_not_reset_the_reminder_gate(): void
    {
        $counselor = $this->counselor();
        $sameDate = now()->addWeek();
        $intervention = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => $sameDate,
            'follow_up_notified_at' => now(),
        ]);

        $this->actingAs($counselor)->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
            'outcome' => 'no_change',
            'follow_up_date' => $sameDate->format('Y-m-d'),
        ]);

        $this->assertNotNull($intervention->fresh()->follow_up_notified_at);
    }

    public function test_marking_resolved_via_quick_update_resolves_the_referral_and_notifies_the_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['status' => 'in_progress', 'counselor_id' => $counselor->id, 'referred_by' => $teacher->id]);
        $intervention = Intervention::factory()->create(['referral_id' => $referral->id, 'counselor_id' => $counselor->id]);

        $this->actingAs($counselor)->patch(route('counselor.interventions.quickUpdate', $intervention->id), [
            'outcome' => 'resolved',
        ]);

        $referral->refresh();
        $this->assertSame('resolved', $referral->status);
        $this->assertNotNull($referral->resolved_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $teacher->id,
            'reference_id' => $referral->id,
            'type' => 'referral_status',
        ]);
    }

    public function test_a_non_owning_counselor_cannot_use_quick_update(): void
    {
        $owner = $this->counselor();
        $someoneElse = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id, 'outcome' => null]);

        $this->actingAs($someoneElse)
            ->patch(route('counselor.interventions.quickUpdate', $intervention->id), ['outcome' => 'improving'])
            ->assertForbidden();

        $this->assertNull($intervention->fresh()->outcome);
    }

    public function test_details_page_shows_the_update_session_form_for_the_owner_only(): void
    {
        $owner = $this->counselor();
        $viewer = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $owner->id]);

        $ownerPage = $this->actingAs($owner)->get(route('counselor.interventions.show', $intervention->id));
        $ownerPage->assertSee('Update Session');

        $viewerPage = $this->actingAs($viewer)->get(route('counselor.interventions.show', $intervention->id));
        $viewerPage->assertDontSee('Update Session');
    }
}
