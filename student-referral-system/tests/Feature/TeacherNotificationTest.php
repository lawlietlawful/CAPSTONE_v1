<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use App\Services\BehavioralReportService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTeacherScenario;
use Tests\TestCase;

/**
 * The teacher activity feed: notifications generated when a report escalates or
 * a counselor changes the status of a referral the teacher filed, plus the
 * read/unread API the header bell drives.
 */
class TeacherNotificationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsTeacherScenario;

    // ── Generation ────────────────────────────────────────────────────────

    public function test_escalation_notifies_the_reporting_teacher(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $this->fakeMlEngine('high');

        app(BehavioralReportService::class)->create($teacher, [
            'student_id'    => $student->id,
            'incident_type' => 'Academic Failure',
            'incident_date' => now()->toDateString(),
            'description'   => 'Failed everything.',
        ]);

        $notification = Notification::where('user_id', $teacher->id)
            ->where('type', 'report_escalated')
            ->first();

        $this->assertNotNull($notification, 'Escalation must notify the reporter.');
        $this->assertFalse((bool) $notification->is_read);
    }

    public function test_counselor_status_change_notifies_the_filing_teacher(): void
    {
        [$teacher, $student] = $this->teacherAdvising();
        $referral = Referral::factory()->create([
            'referred_by' => $teacher->id,
            'student_id'  => $student->id,
            'status'      => 'pending',
        ]);

        $referral->update(['status' => 'resolved']);
        app(NotificationService::class)->referralStatusChanged($referral->fresh());

        $this->assertDatabaseHas('notifications', [
            'user_id' => $teacher->id,
            'type'    => 'referral_status',
        ]);
    }

    public function test_status_change_does_not_notify_a_non_teacher_filer(): void
    {
        // A counselor-filed referral: the filer is a counselor, not a teacher,
        // so no teacher notification should be created.
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create();
        $referral = Referral::factory()->create([
            'referred_by' => $counselor->id,
            'student_id'  => $student->id,
            'status'      => 'in_progress',
        ]);

        app(NotificationService::class)->referralStatusChanged($referral->fresh());

        $this->assertDatabaseCount('notifications', 0);
    }

    // ── API ───────────────────────────────────────────────────────────────

    public function test_notifications_endpoint_returns_feed_and_unread_count(): void
    {
        [$teacher] = $this->teacherAdvising();
        $svc = app(NotificationService::class);
        $svc->notify($teacher->id, 'A', 'first', 'referral_status');
        $svc->notify($teacher->id, 'B', 'second', 'report_escalated');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/teacher/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notifications');
    }

    public function test_mark_one_notification_read(): void
    {
        [$teacher] = $this->teacherAdvising();
        $n = app(NotificationService::class)->notify($teacher->id, 'A', 'x', 'referral_status');
        Sanctum::actingAs($teacher);

        $this->postJson("/api/teacher/notifications/{$n->id}/read")->assertOk();

        $this->assertTrue((bool) $n->fresh()->is_read);
        $this->getJson('/api/teacher/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_mark_all_notifications_read(): void
    {
        [$teacher] = $this->teacherAdvising();
        $svc = app(NotificationService::class);
        $svc->notify($teacher->id, 'A', 'x', 'referral_status');
        $svc->notify($teacher->id, 'B', 'y', 'report_escalated');
        Sanctum::actingAs($teacher);

        $this->postJson('/api/teacher/notifications/read-all')->assertOk();

        $this->getJson('/api/teacher/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_teacher_only_sees_their_own_notifications(): void
    {
        [$teacherA] = $this->teacherAdvising();
        $teacherB = User::factory()->teacher()->create();
        app(NotificationService::class)->notify($teacherB->id, 'Not yours', 'x', 'referral_status');
        Sanctum::actingAs($teacherA);

        $this->getJson('/api/teacher/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'notifications')
            ->assertJsonPath('unread_count', 0);
    }
}
