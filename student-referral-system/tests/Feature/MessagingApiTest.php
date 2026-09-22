<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile Messaging API (Api\MessageController) used by the Student and
 * Teacher Flutter portals: the /api/counselors lookup the compose screens
 * depend on, who may start a thread with whom, reply authorization, and
 * soft-delete visibility in the inbox/sent/show endpoints.
 */
class MessagingApiTest extends TestCase
{
    use RefreshDatabase;

    // ── /api/counselors ──────────────────────────────────────────────────

    public function test_counselors_endpoint_returns_only_counselor_role_users(): void
    {
        $counselor = User::factory()->counselor()->create();
        User::factory()->teacher()->create(); // must not appear in the results
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->getJson('/api/counselors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $counselor->id]);
    }

    // ── Starting a new thread ────────────────────────────────────────────

    public function test_student_cannot_initiate_a_new_thread(): void
    {
        $student = Student::factory()->create()->user;
        $counselor = User::factory()->counselor()->create();
        Sanctum::actingAs($student);

        $this->postJson('/api/messages', [
            'receiver_id' => $counselor->id,
            'content' => 'Hi',
        ])->assertStatus(403);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_teacher_can_message_a_counselor(): void
    {
        $teacher = User::factory()->teacher()->create();
        $counselor = User::factory()->counselor()->create();
        Sanctum::actingAs($teacher);

        $this->postJson('/api/messages', [
            'receiver_id' => $counselor->id,
            'content' => 'Need advice on a student.',
        ])->assertCreated();

        $this->assertDatabaseHas('messages', [
            'sender_id' => $teacher->id,
            'receiver_id' => $counselor->id,
        ]);
    }

    public function test_teacher_cannot_message_another_teacher_or_a_student(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = Student::factory()->create()->user;
        Sanctum::actingAs($teacher);

        $this->postJson('/api/messages', [
            'receiver_id' => $otherTeacher->id, 'content' => 'Hi',
        ])->assertStatus(403);

        $this->postJson('/api/messages', [
            'receiver_id' => $student->id, 'content' => 'Hi',
        ])->assertStatus(403);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_counselor_can_message_a_student_or_teacher(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $teacher = User::factory()->teacher()->create();
        Sanctum::actingAs($counselor);

        $this->postJson('/api/messages', [
            'receiver_id' => $student->id, 'content' => 'Please see me.',
        ])->assertCreated();

        $this->postJson('/api/messages', [
            'receiver_id' => $teacher->id, 'content' => 'Quick question.',
        ])->assertCreated();

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_counselor_cannot_message_another_counselor(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();
        Sanctum::actingAs($counselor);

        $this->postJson('/api/messages', [
            'receiver_id' => $otherCounselor->id, 'content' => 'Hi',
        ])->assertStatus(403);
    }

    public function test_new_thread_requires_a_receiver(): void
    {
        Sanctum::actingAs(User::factory()->teacher()->create());

        $this->postJson('/api/messages', ['content' => 'No receiver given'])
            ->assertStatus(422);
    }

    // ── Replies ───────────────────────────────────────────────────────────

    public function test_student_can_reply_to_a_thread_they_are_part_of(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $thread = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice',
        ]);
        Sanctum::actingAs($student);

        $this->postJson('/api/messages', [
            'parent_id' => $thread->id, 'content' => 'Received, thank you.',
        ])->assertCreated();

        $this->assertDatabaseHas('messages', [
            'parent_id' => $thread->id, 'sender_id' => $student->id, 'receiver_id' => $counselor->id,
        ]);
    }

    public function test_reply_from_someone_outside_the_thread_is_rejected(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $outsider = Student::factory()->create()->user;
        $thread = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice',
        ]);
        Sanctum::actingAs($outsider);

        $this->postJson('/api/messages', [
            'parent_id' => $thread->id, 'content' => 'Butting in.',
        ])->assertStatus(403);
    }

    // ── Inbox / sent visibility (soft delete) ────────────────────────────

    public function test_inbox_excludes_threads_deleted_by_the_receiver(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Visible',
        ]);
        Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Hidden',
            'deleted_by_receiver' => true,
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['content' => 'Visible'])
            ->assertJsonMissing(['content' => 'Hidden']);
    }

    public function test_sent_excludes_threads_deleted_by_the_sender(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Visible',
        ]);
        Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Hidden',
            'deleted_by_sender' => true,
        ]);
        Sanctum::actingAs($counselor);

        $this->getJson('/api/messages/sent')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['content' => 'Visible']);
    }

    public function test_show_returns_404_for_a_thread_the_viewer_already_deleted(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $thread = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice',
            'deleted_by_receiver' => true,
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/messages/' . $thread->id)->assertStatus(404);
    }

    public function test_show_rejects_a_user_outside_the_thread(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $outsider = Student::factory()->create()->user;
        $thread = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice',
        ]);
        Sanctum::actingAs($outsider);

        $this->getJson('/api/messages/' . $thread->id)->assertStatus(403);
    }

    public function test_show_marks_the_thread_read_for_the_receiver(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $thread = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice',
        ]);
        Sanctum::actingAs($student);

        $this->getJson('/api/messages/' . $thread->id)->assertOk();

        $this->assertNotNull($thread->fresh()->read_at);
    }

    // ── Response shaping ─────────────────────────────────────────────────
    // Responses used to be raw model dumps, exposing deleted_by_sender/
    // deleted_by_receiver — internal per-side bookkeeping the mobile client
    // has no use for. Every endpoint now shapes its output explicitly.

    public function test_index_response_does_not_expose_internal_delete_flags(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        Message::create(['sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/messages')->assertOk();

        $response->assertJsonMissingPath('data.0.deleted_by_sender');
        $response->assertJsonMissingPath('data.0.deleted_by_receiver');
    }

    public function test_show_response_includes_subject_and_shaped_replies_without_delete_flags(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $thread = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id,
            'subject' => 'Reminder', 'content' => 'Please see me.',
        ]);
        Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id,
            'parent_id' => $thread->id, 'content' => 'On my way.',
        ]);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/messages/' . $thread->id)->assertOk();

        $response->assertJsonPath('subject', 'Reminder');
        $response->assertJsonCount(1, 'replies');
        $response->assertJsonPath('replies.0.content', 'On my way.');
        $response->assertJsonMissingPath('deleted_by_sender');
        $response->assertJsonMissingPath('replies.0.deleted_by_sender');
    }

    public function test_store_response_does_not_expose_internal_delete_flags(): void
    {
        $counselor = User::factory()->counselor()->create();
        Sanctum::actingAs(User::factory()->teacher()->create());

        $response = $this->postJson('/api/messages', [
            'receiver_id' => $counselor->id, 'content' => 'Need advice.',
        ])->assertCreated();

        $response->assertJsonMissingPath('data.deleted_by_sender');
        $response->assertJsonMissingPath('data.deleted_by_receiver');
    }
}
