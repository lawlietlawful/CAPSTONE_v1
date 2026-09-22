<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Counselor "Notices" web UI (Counselor\MessageController): the
 * counselor-only gate, who a counselor may message, inbox/sent scoping, and
 * the soft-delete lifecycle behind the AJAX delete button — including that
 * a message only truly disappears once BOTH sides have deleted it.
 */
class CounselorMessagingTest extends TestCase
{
    use RefreshDatabase;

    // ── Access gate ───────────────────────────────────────────────────────

    public function test_non_counselor_cannot_access_messages(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get(route('counselor.messages.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_access_messages(): void
    {
        $student = Student::factory()->create()->user;

        $this->actingAs($student)->get(route('counselor.messages.index'))
            ->assertForbidden();
    }

    // ── Sending ───────────────────────────────────────────────────────────

    public function test_counselor_can_send_a_notice_to_a_student(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;

        $this->actingAs($counselor)->post(route('counselor.messages.store'), [
            'receiver_id' => $student->id,
            'subject' => 'Reminder',
            'content' => 'Please see the office.',
        ])->assertRedirect(route('counselor.messages.index'));

        $this->assertDatabaseHas('messages', [
            'sender_id' => $counselor->id,
            'receiver_id' => $student->id,
            'subject' => 'Reminder',
        ]);
    }

    public function test_counselor_cannot_send_a_notice_to_another_counselor(): void
    {
        $counselor = User::factory()->counselor()->create();
        $otherCounselor = User::factory()->counselor()->create();

        $this->actingAs($counselor)->post(route('counselor.messages.store'), [
            'receiver_id' => $otherCounselor->id,
            'content' => 'Hi',
        ])->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_counselor_can_reply_within_an_existing_thread(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $thread = Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id, 'content' => 'A question',
        ]);

        $this->actingAs($counselor)->post(route('counselor.messages.store'), [
            'parent_id' => $thread->id,
            'content' => 'Here is the answer.',
        ])->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'parent_id' => $thread->id, 'sender_id' => $counselor->id, 'receiver_id' => $student->id,
        ]);
    }

    // ── Inbox / sent scoping ──────────────────────────────────────────────

    public function test_index_hides_threads_the_counselor_deleted_on_their_side(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id, 'content' => 'Visible', 'subject' => 'A',
        ]);
        Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id, 'content' => 'Hidden', 'subject' => 'B',
            'deleted_by_receiver' => true,
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.messages.index'));

        $response->assertOk();
        $response->assertViewHas('inbox', function ($inbox) {
            return $inbox->total() === 1;
        });
    }

    // ── Subject display ──────────────────────────────────────────────────
    // The subject field was validated and stored server-side but had no
    // compose input and was never rendered anywhere — every message was
    // subject-less by construction. Now it's collected and shown.

    public function test_a_messages_subject_is_shown_in_the_inbox_list(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id,
            'subject' => 'Question about my grade', 'content' => 'Can you help me?',
        ]);

        $this->actingAs($counselor)->get(route('counselor.messages.index'))
            ->assertSee('Question about my grade');
    }

    public function test_a_messages_subject_is_shown_in_the_thread_header(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $message = Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id,
            'subject' => 'Question about my grade', 'content' => 'Can you help me?',
        ]);

        $this->actingAs($counselor)->get(route('counselor.messages.show', $message))
            ->assertSee('Question about my grade');
    }

    public function test_thread_header_falls_back_to_a_generic_title_with_no_subject(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $message = Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id, 'content' => 'No subject here.',
        ]);

        $this->actingAs($counselor)->get(route('counselor.messages.show', $message))
            ->assertSee('Notice Thread');
    }

    // ── Search ────────────────────────────────────────────────────────────

    public function test_search_filters_the_inbox_by_sender_name(): void
    {
        $counselor = User::factory()->counselor()->create();
        $juan = User::factory()->create(['role' => 'student', 'name' => 'Juan Dela Cruz']);
        $maria = User::factory()->create(['role' => 'student', 'name' => 'Maria Santos']);
        Message::create(['sender_id' => $juan->id, 'receiver_id' => $counselor->id, 'content' => 'Hello']);
        Message::create(['sender_id' => $maria->id, 'receiver_id' => $counselor->id, 'content' => 'Hi there']);

        $response = $this->actingAs($counselor)->get(route('counselor.messages.index', ['search' => 'Juan']));

        $response->assertViewHas('inbox', fn ($inbox) => $inbox->total() === 1);
    }

    public function test_search_filters_the_inbox_by_message_content(): void
    {
        $counselor = User::factory()->counselor()->create();
        $studentA = Student::factory()->create()->user;
        $studentB = Student::factory()->create()->user;
        Message::create(['sender_id' => $studentA->id, 'receiver_id' => $counselor->id, 'content' => 'About my grades']);
        Message::create(['sender_id' => $studentB->id, 'receiver_id' => $counselor->id, 'content' => 'Unrelated question']);

        $response = $this->actingAs($counselor)->get(route('counselor.messages.index', ['search' => 'grades']));

        $response->assertViewHas('inbox', fn ($inbox) => $inbox->total() === 1);
    }

    public function test_search_on_sent_tab_matches_the_receivers_name(): void
    {
        $counselor = User::factory()->counselor()->create();
        $juan = User::factory()->create(['role' => 'student', 'name' => 'Juan Dela Cruz']);
        $maria = User::factory()->create(['role' => 'student', 'name' => 'Maria Santos']);
        Message::create(['sender_id' => $counselor->id, 'receiver_id' => $juan->id, 'content' => 'Notice A']);
        Message::create(['sender_id' => $counselor->id, 'receiver_id' => $maria->id, 'content' => 'Notice B']);

        $response = $this->actingAs($counselor)->get(route('counselor.messages.index', ['tab' => 'sent', 'search' => 'Maria']));

        $response->assertViewHas('sent', fn ($sent) => $sent->total() === 1);
    }

    // ── Unread badge ──────────────────────────────────────────────────────

    public function test_unread_badge_counts_unread_messages_not_total_threads(): void
    {
        $counselor = User::factory()->counselor()->create();
        $studentA = Student::factory()->create()->user;
        $studentB = Student::factory()->create()->user;
        Message::create([
            'sender_id' => $studentA->id, 'receiver_id' => $counselor->id, 'content' => 'Unread one',
        ]);
        Message::create([
            'sender_id' => $studentB->id, 'receiver_id' => $counselor->id, 'content' => 'Already read', 'read_at' => now(),
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.messages.index'));

        // Two threads exist, but only one is actually unread — the badge
        // must reflect that, not $inbox->total() (which would say 2).
        $response->assertViewHas('unreadCount', 1);
    }

    public function test_unread_badge_is_zero_once_everything_has_been_read(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id, 'content' => 'Read already', 'read_at' => now(),
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.messages.index'));

        $response->assertViewHas('unreadCount', 0);
        $response->assertDontSee('bg-blue-100 text-blue-700 py-0.5 px-2 rounded-full text-xs">1', false);
    }

    public function test_unread_badge_includes_an_unread_reply_on_a_thread_the_counselor_started(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $thread = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Please see me.',
        ]);
        // The student's reply lives under the counselor's "Sent" tab
        // structurally (parent was sent by the counselor), but it's still
        // an unread message the counselor hasn't seen.
        Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id, 'content' => 'Okay.', 'parent_id' => $thread->id,
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.messages.index'));

        $response->assertViewHas('unreadCount', 1);
    }

    // ── Delete lifecycle ──────────────────────────────────────────────────

    public function test_deleting_a_message_only_hides_it_for_that_side(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $message = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice',
        ]);

        $this->actingAs($counselor)
            ->deleteJson(route('counselor.messages.destroy', $message))
            ->assertOk()
            ->assertJson(['success' => true]);

        $message->refresh();
        $this->assertTrue($message->deleted_by_sender);
        $this->assertFalse($message->deleted_by_receiver);
        $this->assertNotNull($message->id, 'row must still exist — the other side has not deleted it yet');
    }

    public function test_message_is_hard_deleted_once_both_sides_have_deleted_it(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $message = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice',
            'deleted_by_receiver' => true, // student already deleted their side
        ]);

        $this->actingAs($counselor)
            ->deleteJson(route('counselor.messages.destroy', $message))
            ->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_a_user_outside_the_thread_cannot_delete_it(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $outsider = User::factory()->counselor()->create();
        $message = Message::create([
            'sender_id' => $counselor->id, 'receiver_id' => $student->id, 'content' => 'Notice',
        ]);

        $this->actingAs($outsider)
            ->deleteJson(route('counselor.messages.destroy', $message))
            ->assertForbidden();

        $message->refresh();
        $this->assertFalse($message->deleted_by_sender);
        $this->assertFalse($message->deleted_by_receiver);
    }

    // ── Viewing ───────────────────────────────────────────────────────────

    public function test_viewing_a_thread_already_deleted_by_this_user_is_not_found(): void
    {
        $counselor = User::factory()->counselor()->create();
        $student = Student::factory()->create()->user;
        $message = Message::create([
            'sender_id' => $student->id, 'receiver_id' => $counselor->id, 'content' => 'Notice',
            'deleted_by_receiver' => true,
        ]);

        $this->actingAs($counselor)->get(route('counselor.messages.show', $message))
            ->assertNotFound();
    }
}
