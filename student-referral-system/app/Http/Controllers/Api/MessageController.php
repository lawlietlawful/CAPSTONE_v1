<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * Get the inbox (received top-level messages/threads).
     */
    public function index()
    {
        $user = auth()->user();

        // Get top-level messages where user is the receiver
        $messages = Message::with(['sender:id,name,role'])
            ->where('receiver_id', $user->id)
            ->whereNull('parent_id')
            ->where('deleted_by_receiver', false)
            ->latest()
            ->paginate(15)
            ->through(fn (Message $message) => $this->shapeMessage($message));

        return response()->json($messages);
    }

    /**
     * Get sent top-level messages.
     */
    public function sent()
    {
        $user = auth()->user();

        $messages = Message::with(['receiver:id,name,role'])
            ->where('sender_id', $user->id)
            ->whereNull('parent_id')
            ->where('deleted_by_sender', false)
            ->latest()
            ->paginate(15)
            ->through(fn (Message $message) => $this->shapeMessage($message));

        return response()->json($messages);
    }

    /**
     * Get a specific thread (parent + all replies).
     */
    public function show($id)
    {
        $user = auth()->user();

        $message = Message::with([
            'sender:id,name,role',
            'receiver:id,name,role',
            'replies.sender:id,name,role',
            'replies.receiver:id,name,role'
        ])->findOrFail($id);

        // Ensure user is part of the thread
        if ($message->sender_id !== $user->id && $message->receiver_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to this thread.'], 403);
        }

        // Treat a thread deleted by this user the same as one they were never part of.
        if (($message->sender_id === $user->id && $message->deleted_by_sender) ||
            ($message->receiver_id === $user->id && $message->deleted_by_receiver)) {
            return response()->json(['message' => 'Thread not found.'], 404);
        }

        // Mark as read if user is the receiver of the parent message
        if ($message->receiver_id === $user->id && is_null($message->read_at)) {
            $message->update(['read_at' => now()]);
        }

        // Mark replies as read where user is the receiver
        foreach ($message->replies as $reply) {
            if ($reply->receiver_id === $user->id && is_null($reply->read_at)) {
                $reply->update(['read_at' => now()]);
            }
        }

        return response()->json($this->shapeMessage($message->fresh([
            'sender:id,name,role', 'receiver:id,name,role', 'replies.sender:id,name,role', 'replies.receiver:id,name,role',
        ]), withReplies: true));
    }

    /**
     * The client-facing shape of a message: id/subject/content/timestamps
     * and the other party's public info. Deliberately leaves out
     * deleted_by_sender/deleted_by_receiver — internal per-side bookkeeping
     * the mobile client has no use for and shouldn't need to know exists.
     */
    protected function shapeMessage(Message $message, bool $withReplies = false): array
    {
        $shaped = [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'subject' => $message->subject,
            'content' => $message->content,
            'read_at' => $message->read_at,
            'parent_id' => $message->parent_id,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
            'sender' => $message->relationLoaded('sender') ? $this->shapeUser($message->sender) : null,
            'receiver' => $message->relationLoaded('receiver') ? $this->shapeUser($message->receiver) : null,
        ];

        if ($withReplies) {
            $shaped['replies'] = $message->replies->map(fn (Message $reply) => $this->shapeMessage($reply))->all();
        }

        return $shaped;
    }

    protected function shapeUser(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'role' => $user->role] : null;
    }

    /**
     * Send a new message or reply.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:messages,id',
            // receiver_id is required if it's a new thread (no parent_id)
            'receiver_id' => 'required_without:parent_id|exists:users,id',
            'subject' => 'nullable|string|max:255',
        ]);

        // If it's a new thread
        if (!$request->parent_id) {
            // Students cannot initiate messages.
            if ($user->role === 'student') {
                return response()->json(['message' => 'Students cannot initiate new messages. You may only reply to notices.'], 403);
            }

            // Teachers may only message a counselor; counselors may message a
            // student or teacher. See Message::canInitiate().
            $receiver = User::find($request->receiver_id);
            if (!$receiver || !Message::canInitiate($user, $receiver)) {
                return response()->json(['message' => 'You are not allowed to message this recipient.'], 403);
            }

            $message = Message::create([
                'sender_id' => $user->id,
                'receiver_id' => $request->receiver_id,
                'subject' => $request->subject,
                'content' => $request->content,
            ]);

            return response()->json([
                'message' => 'Message sent successfully.',
                'data' => $this->shapeMessage($message->load('receiver:id,name,role')),
            ], 201);
        }

        // If it's a reply
        $parent = Message::findOrFail($request->parent_id);

        // Ensure user is part of the original thread
        if ($parent->sender_id !== $user->id && $parent->receiver_id !== $user->id) {
            return response()->json(['message' => 'You are not part of this thread.'], 403);
        }

        // The receiver of the reply is the OTHER person in the thread
        $receiverId = ($parent->sender_id === $user->id) ? $parent->receiver_id : $parent->sender_id;

        $reply = Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'parent_id' => $parent->id,
            'content' => $request->content,
        ]);

        return response()->json([
            'message' => 'Reply sent successfully.',
            'data' => $this->shapeMessage($reply->load('sender:id,name,role')),
        ], 201);
    }
}
