<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The teacher web portal's bell was worse off than admin/counselor's: the
 * red "unread" dot was hardcoded on unconditionally (no isset() guard at
 * all), the button had no click behavior, and there was no /teacher/
 * notifications route — despite teachers being the one role that already
 * received real notifications (NotificationService::reportEscalated /
 * referralStatusChanged) via the mobile app's API. This locks down the new
 * web page and dropdown.
 */
class TeacherNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_only_sees_their_own_notifications(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();

        Notification::create([
            'user_id' => $teacher->id, 'title' => 'Mine', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);
        Notification::create([
            'user_id' => $other->id, 'title' => 'Not mine', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.notifications.index'));

        $response->assertOk();
        $response->assertSee('Mine');
        $response->assertDontSee('Not mine');
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_the_referrals_list(): void
    {
        $teacher = User::factory()->teacher()->create();
        $notification = Notification::create([
            'user_id' => $teacher->id, 'title' => 'Report escalated to Guidance', 'message' => 'x',
            'type' => 'report_escalated', 'reference_type' => 'referral', 'reference_id' => 1,
            'is_read' => false,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.notifications.show', $notification->id));

        $response->assertRedirect(route('teacher.referrals.index'));
        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_a_teacher_cannot_open_another_teachers_notification(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $notification = Notification::create([
            'user_id' => $other->id, 'title' => 'x', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        $this->actingAs($teacher)->get(route('teacher.notifications.show', $notification->id))
            ->assertForbidden();
    }

    public function test_a_counselor_cannot_reach_the_teacher_notifications_route(): void
    {
        $counselor = User::factory()->counselor()->create();

        $this->actingAs($counselor)->get(route('teacher.notifications.index'))
            ->assertForbidden();
    }

    public function test_mark_all_read_only_affects_the_current_teachers_notifications(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $mine = Notification::create([
            'user_id' => $teacher->id, 'title' => 'x', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);
        $theirs = Notification::create([
            'user_id' => $other->id, 'title' => 'x', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        $this->actingAs($teacher)->post(route('teacher.notifications.markAllRead'))
            ->assertRedirect();

        $this->assertTrue($mine->fresh()->is_read);
        $this->assertFalse($theirs->fresh()->is_read);
    }

    // ── The header bell ───────────────────────────────────────────────────

    /**
     * The badge <span> itself is now always structurally present (Alpine's
     * x-show toggles it client-side after polling), so what actually proves
     * correctness server-side is the seeded unreadCount value the bell's
     * x-data is initialized with — not the presence of the "bg-red-500"
     * class, which no longer varies. Previously this badge was hardcoded on
     * regardless of actual state; this locks down that it's real now.
     */
    public function test_the_bell_seeds_a_zero_unread_count_with_no_notifications(): void
    {
        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('unreadCount: 0', false);
    }

    public function test_the_bell_seeds_the_real_unread_count_once_a_notification_exists(): void
    {
        $teacher = User::factory()->teacher()->create();
        Notification::create([
            'user_id' => $teacher->id, 'title' => 'x', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('unreadCount: 1', false);
    }

    public function test_the_bell_dropdown_shows_recent_notifications_on_any_teacher_page(): void
    {
        $teacher = User::factory()->teacher()->create();
        Notification::create([
            'user_id' => $teacher->id, 'title' => 'Dropdown preview test', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        // A page other than the notifications index — proves the dropdown's
        // content comes from the shared View Composer.
        $response = $this->actingAs($teacher)->get(route('teacher.referrals.index'));

        $response->assertOk();
        $response->assertSee('Dropdown preview test');
    }

    public function test_the_bell_dropdown_never_shows_another_teachers_notifications(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        Notification::create([
            'user_id' => $other->id, 'title' => 'Someone elses notification', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.referrals.index'));

        $response->assertOk();
        $response->assertDontSee('Someone elses notification');
    }

    // ── Polling (real-time updates without a manual page refresh) ──────────

    public function test_polling_returns_the_real_unread_count_and_recent_notifications(): void
    {
        $teacher = User::factory()->teacher()->create();
        Notification::create([
            'user_id' => $teacher->id, 'title' => 'Fresh one', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        $response = $this->actingAs($teacher)->getJson(route('teacher.notifications.poll'));

        $response->assertOk();
        $response->assertJson(['unread_count' => 1]);
        $response->assertJsonFragment(['title' => 'Fresh one']);
    }

    public function test_polling_never_returns_another_teachers_notifications(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        Notification::create([
            'user_id' => $other->id, 'title' => 'Not mine', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        $response = $this->actingAs($teacher)->getJson(route('teacher.notifications.poll'));

        $response->assertOk();
        $response->assertJson(['unread_count' => 0]);
        $response->assertJsonMissing(['title' => 'Not mine']);
    }

    public function test_mark_all_read_returns_json_when_called_via_ajax(): void
    {
        $teacher = User::factory()->teacher()->create();
        Notification::create([
            'user_id' => $teacher->id, 'title' => 'x', 'message' => 'x',
            'type' => 'report_escalated', 'is_read' => false,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('teacher.notifications.markAllRead'));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('notifications', ['user_id' => $teacher->id, 'is_read' => true]);
    }
}
