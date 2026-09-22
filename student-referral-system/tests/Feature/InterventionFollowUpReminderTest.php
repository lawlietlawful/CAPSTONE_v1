<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A scheduled follow-up used to have no reminder at all — a counselor only
 * found out it was due or overdue by visiting the Interventions page. The
 * "interventions:send-followup-reminders" command (run daily) notifies the
 * logging counselor once per record, gated by follow_up_notified_at so it
 * never repeats for the same intervention.
 */
class InterventionFollowUpReminderTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_notifies_for_a_follow_up_due_today(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->format('Y-m-d'),
        ]);

        $this->artisan('interventions:send-followup-reminders')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $counselor->id,
            'reference_type' => 'intervention',
            'reference_id' => $intervention->id,
            'type' => 'intervention_followup',
        ]);
        $this->assertNotNull($intervention->fresh()->follow_up_notified_at);
    }

    public function test_notifies_for_an_overdue_follow_up(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subWeek(),
        ]);

        $this->artisan('interventions:send-followup-reminders');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $counselor->id,
            'type' => 'intervention_followup',
        ]);
    }

    public function test_does_not_notify_twice_for_the_same_intervention(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subDays(2),
        ]);

        $this->artisan('interventions:send-followup-reminders');
        $this->artisan('interventions:send-followup-reminders');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_does_not_notify_for_a_future_follow_up(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->addWeek(),
        ]);

        $this->artisan('interventions:send-followup-reminders');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_does_not_notify_for_an_already_resolved_intervention(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->subWeek(),
            'outcome' => 'resolved',
        ]);

        $this->artisan('interventions:send-followup-reminders');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_does_not_notify_when_there_is_no_follow_up_date(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => null,
        ]);

        $this->artisan('interventions:send-followup-reminders');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_clicking_the_notification_goes_to_the_intervention(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create([
            'counselor_id' => $counselor->id,
            'follow_up_date' => now()->format('Y-m-d'),
        ]);

        $this->artisan('interventions:send-followup-reminders');

        $notification = \App\Models\Notification::where('reference_type', 'intervention')->firstOrFail();

        $this->actingAs($counselor)
            ->get(route('admin.notifications.show', $notification->id))
            ->assertRedirect(route('counselor.interventions.show', $intervention->id));
    }
}
