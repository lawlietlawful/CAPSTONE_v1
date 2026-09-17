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
