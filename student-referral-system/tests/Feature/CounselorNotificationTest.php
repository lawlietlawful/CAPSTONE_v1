<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header bell used to be pure decoration: no click behavior, and no
 * event in the system ever created a Notification for an admin/counselor
 * account (only teachers got them). This locks down the new counselor-facing
 * notification — fired when a referral becomes pending — end to end: who
 * gets notified, the notifications page itself, and the read/redirect flow.
 */
class CounselorNotificationTest extends TestCase
{
    use RefreshDatabase;

    // ── NotificationService::newPendingReferral ─────────────────────────────

    public function test_an_unclaimed_referral_notifies_every_counselor(): void
    {
        $teacher = User::factory()->teacher()->create();
        $counselorA = User::factory()->counselor()->create();
        $counselorB = User::factory()->counselor()->create();
        $referral = Referral::factory()->create(['referred_by' => $teacher->id, 'counselor_id' => null]);

        app(NotificationService::class)->newPendingReferral($referral);

        $this->assertDatabaseHas('notifications', ['user_id' => $counselorA->id, 'reference_id' => $referral->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $counselorB->id, 'reference_id' => $referral->id]);
    }

    public function test_a_referral_assigned_at_creation_only_notifies_that_counselor(): void
    {
        $teacher = User::factory()->teacher()->create();
        $assigned = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();
        $referral = Referral::factory()->create(['referred_by' => $teacher->id, 'counselor_id' => $assigned->id]);

        app(NotificationService::class)->newPendingReferral($referral);

        $this->assertDatabaseHas('notifications', ['user_id' => $assigned->id, 'reference_id' => $referral->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $otherCounselor->id, 'reference_id' => $referral->id]);
    }

    public function test_a_counselor_filing_their_own_referral_does_not_notify_themselves(): void
    {
        $counselor = User::factory()->counselor()->create();
        $referral = Referral::factory()->create(['referred_by' => $counselor->id, 'counselor_id' => $counselor->id]);

        app(NotificationService::class)->newPendingReferral($referral);

        $this->assertDatabaseMissing('notifications', ['user_id' => $counselor->id, 'reference_id' => $referral->id]);
    }

    public function test_a_counselor_assigning_another_counselor_notifies_only_that_counselor(): void
    {
        $filer = User::factory()->counselor()->create();
        $assigned = User::factory()->counselor()->create();
        $referral = Referral::factory()->create(['referred_by' => $filer->id, 'counselor_id' => $assigned->id]);

        app(NotificationService::class)->newPendingReferral($referral);

        $this->assertDatabaseHas('notifications', ['user_id' => $assigned->id, 'reference_id' => $referral->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $filer->id, 'reference_id' => $referral->id]);
    }

    // ── End to end: filing a referral through the counselor controller ──────

    public function test_filing_a_referral_via_the_counselor_controller_notifies_unclaimed_referrals(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();
        $student = Student::factory()->create();

        $this->actingAs($counselor)->post(route('counselor.referrals.store'), [
            'student_id'    => $student->id,
            'referral_type' => 'Tardiness',
            'reason'        => 'Repeated lateness.',
            'priority'      => 'low',
        ])->assertRedirect(route('counselor.referrals.index'));

        $referral = Referral::where('student_id', $student->id)->firstOrFail();
        $this->assertDatabaseHas('notifications', ['user_id' => $otherCounselor->id, 'reference_id' => $referral->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $counselor->id, 'reference_id' => $referral->id]);
    }

    // ── The notifications page ───────────────────────────────────────────────

    public function test_a_counselor_only_sees_their_own_notifications(): void
    {
        $counselor = User::factory()->counselor()->create();
        $other = User::factory()->counselor()->create();

        $mine = Notification::create([
            'user_id' => $counselor->id, 'title' => 'Mine', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);
        Notification::create([
            'user_id' => $other->id, 'title' => 'Not mine', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        $response = $this->actingAs($counselor)->get(route('admin.notifications.index'));

        $response->assertOk();
        $response->assertSee('Mine');
        $response->assertDontSee('Not mine');
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_the_referral(): void
    {
        $counselor = User::factory()->counselor()->create();
        $referral = Referral::factory()->create(['counselor_id' => $counselor->id]);
        $notification = Notification::create([
            'user_id' => $counselor->id, 'title' => 'New referral needs review', 'message' => 'x',
            'type' => 'referral_pending', 'reference_type' => 'referral', 'reference_id' => $referral->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($counselor)->get(route('admin.notifications.show', $notification->id));

        $response->assertRedirect(route('counselor.referrals.show', $referral->id));
        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_a_super_admin_opening_a_notification_is_redirected_to_the_admin_referral_route(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $referral = Referral::factory()->create();
        $notification = Notification::create([
            'user_id' => $superAdmin->id, 'title' => 'x', 'message' => 'x',
            'type' => 'referral_pending', 'reference_type' => 'referral', 'reference_id' => $referral->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.notifications.show', $notification->id));

        $response->assertRedirect(route('admin.referrals.show', $referral->id));
    }

    public function test_a_counselor_cannot_open_another_users_notification(): void
    {
        $counselor = User::factory()->counselor()->create();
        $other = User::factory()->counselor()->create();
        $notification = Notification::create([
            'user_id' => $other->id, 'title' => 'x', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        $this->actingAs($counselor)->get(route('admin.notifications.show', $notification->id))
            ->assertForbidden();
    }

    public function test_mark_all_read_only_affects_the_current_users_notifications(): void
    {
        $counselor = User::factory()->counselor()->create();
        $other = User::factory()->counselor()->create();
        $mine = Notification::create([
            'user_id' => $counselor->id, 'title' => 'x', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);
        $theirs = Notification::create([
            'user_id' => $other->id, 'title' => 'x', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        $this->actingAs($counselor)->post(route('admin.notifications.markAllRead'))
            ->assertRedirect();

        $this->assertTrue($mine->fresh()->is_read);
        $this->assertFalse($theirs->fresh()->is_read);
    }

    // ── The header bell badge ────────────────────────────────────────────────

    /**
     * The badge <span> itself is now always structurally present (Alpine's
     * x-show toggles it client-side after polling), so what actually proves
     * correctness server-side is the seeded unreadCount value the bell's
     * x-data is initialized with — not the presence of the "bg-red-500"
     * class, which no longer varies.
     */
    public function test_the_bell_seeds_the_real_unread_count_on_any_counselor_page(): void
    {
        $counselor = User::factory()->counselor()->create();
        Notification::create([
            'user_id' => $counselor->id, 'title' => 'x', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        // A page other than the notifications index itself — proves the
        // count comes from the shared View Composer, not a one-off
        // controller computation.
        $response = $this->actingAs($counselor)->get('/admin/students');

        $response->assertOk();
        $response->assertSee('unreadCount: 1', false);
    }

    public function test_the_bell_seeds_a_zero_unread_count_when_everything_is_read(): void
    {
        $counselor = User::factory()->counselor()->create();

        $response = $this->actingAs($counselor)->get('/admin/students');

        $response->assertOk();
        $response->assertSee('unreadCount: 0', false);
    }

    // ── The header bell's dropdown preview ──────────────────────────────────

    public function test_the_bell_dropdown_shows_recent_notifications_on_any_page(): void
    {
        $counselor = User::factory()->counselor()->create();
        Notification::create([
            'user_id' => $counselor->id, 'title' => 'Dropdown preview test', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        // A page other than the notifications index — proves the dropdown's
        // content comes from the shared View Composer, not the index route.
        $response = $this->actingAs($counselor)->get('/admin/courses');

        $response->assertOk();
        $response->assertSee('Dropdown preview test');
    }

    public function test_the_bell_dropdown_never_shows_another_users_notifications(): void
    {
        $counselor = User::factory()->counselor()->create();
        $other = User::factory()->counselor()->create();
        Notification::create([
            'user_id' => $other->id, 'title' => 'Someone elses notification', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        $response = $this->actingAs($counselor)->get('/admin/courses');

        $response->assertOk();
        $response->assertDontSee('Someone elses notification');
    }

    // ── Polling (real-time updates without a manual page refresh) ──────────

    public function test_polling_returns_the_real_unread_count_and_recent_notifications(): void
    {
        $counselor = User::factory()->counselor()->create();
        Notification::create([
            'user_id' => $counselor->id, 'title' => 'Fresh one', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        $response = $this->actingAs($counselor)->getJson(route('admin.notifications.poll'));

        $response->assertOk();
        $response->assertJson(['unread_count' => 1]);
        $response->assertJsonFragment(['title' => 'Fresh one']);
    }

    public function test_polling_never_returns_another_users_notifications(): void
    {
        $counselor = User::factory()->counselor()->create();
        $other = User::factory()->counselor()->create();
        Notification::create([
            'user_id' => $other->id, 'title' => 'Not mine', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        $response = $this->actingAs($counselor)->getJson(route('admin.notifications.poll'));

        $response->assertOk();
        $response->assertJson(['unread_count' => 0]);
        $response->assertJsonMissing(['title' => 'Not mine']);
    }

    public function test_mark_all_read_returns_json_when_called_via_ajax(): void
    {
        $counselor = User::factory()->counselor()->create();
        Notification::create([
            'user_id' => $counselor->id, 'title' => 'x', 'message' => 'x',
            'type' => 'referral_pending', 'is_read' => false,
        ]);

        $response = $this->actingAs($counselor)->postJson(route('admin.notifications.markAllRead'));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('notifications', ['user_id' => $counselor->id, 'is_read' => true]);
    }
}
