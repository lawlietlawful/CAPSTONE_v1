<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->string('search')->trim()->value() ?: null;

        // Get received top-level messages
        $inbox = Message::with(['sender:id,name,role'])
            ->where('receiver_id', $user->id)
            ->whereNull('parent_id')
            ->where('deleted_by_receiver', false)
            ->when($search, fn (Builder $q) => $this->applySearch($q, $search, 'sender'))
            ->latest()
            ->paginate(10)
            ->appends($request->query());

        // Get sent top-level messages
        $sent = Message::with(['receiver:id,name,role'])
            ->where('sender_id', $user->id)
            ->whereNull('parent_id')
            ->where('deleted_by_sender', false)
            ->when($search, fn (Builder $q) => $this->applySearch($q, $search, 'receiver'))
            ->latest()
            ->paginate(10)
            ->appends($request->query());

        // The Inbox badge used to show $inbox->total() — the number of
        // threads, read or not, so it never went away even after reading
        // everything. This counts actually-unread messages instead,
        // including unread replies to a thread the counselor started
        // (which lives under "Sent", not "Inbox", but is still something
        // they haven't seen yet).
        $unreadCount = Message::where('receiver_id', $user->id)
            ->where('deleted_by_receiver', false)
            ->whereNull('read_at')
            ->count();

        // For composing new messages, get a list of students and teachers
        $students = User::where('role', 'student')->get();
        $teachers = User::where('role', 'teacher')->get();

        return view('counselor.messages.index', compact('inbox', 'sent', 'unreadCount', 'students', 'teachers', 'search'));
    }

    /**
     * Match $search against the other party's name, or the message's own
     * subject/content — the three things a counselor would actually
     * remember about a conversation they're trying to find again.
     */
    protected function applySearch(Builder $query, string $search, string $otherParty): Builder
    {
        return $query->where(function (Builder $q) use ($search, $otherParty) {
            $q->whereHas($otherParty, fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%");
        });
    }

    public function show($id)
    {
        $user = auth()->user();

        $message = Message::with([
            'sender', 'receiver', 'replies.sender', 'replies.receiver'
        ])->findOrFail($id);

        if ($message->sender_id !== $user->id && $message->receiver_id !== $user->id) {
            abort(403);
        }

        // Prevent viewing if deleted by this user
        if (($message->sender_id === $user->id && $message->deleted_by_sender) || 
            ($message->receiver_id === $user->id && $message->deleted_by_receiver)) {
            abort(404);
        }

        // Mark as read
        if ($message->receiver_id === $user->id && is_null($message->read_at)) {
            $message->update(['read_at' => now()]);
        }

        foreach ($message->replies as $reply) {
            if ($reply->receiver_id === $user->id && is_null($reply->read_at)) {
                $reply->update(['read_at' => now()]);
            }
        }

        return view('counselor.messages.show', compact('message'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:messages,id',
            'receiver_id' => 'required_without:parent_id|exists:users,id',
            'subject' => 'nullable|string|max:255',
        ]);

        if (!$request->parent_id) {
            $receiver = User::find($request->receiver_id);
            if (!$receiver || !Message::canInitiate($user, $receiver)) {
                abort(403, 'You are not allowed to message this recipient.');
            }

            Message::create([
                'sender_id' => $user->id,
                'receiver_id' => $request->receiver_id,
                'subject' => $request->subject,
                'content' => $request->content,
            ]);
            return redirect()->route('counselor.messages.index', $request->has('modal') ? ['modal' => 1] : [])
                ->with('success', 'Notice sent successfully.');
        }

        $parent = Message::findOrFail($request->parent_id);
        
        if ($parent->sender_id !== $user->id && $parent->receiver_id !== $user->id) {
            abort(403);
        }

        $receiverId = ($parent->sender_id === $user->id) ? $parent->receiver_id : $parent->sender_id;

        Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'parent_id' => $parent->id,
            'content' => $request->content,
        ]);

        return redirect()->back()->with('success', 'Reply sent successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $user = auth()->user();
        $message = Message::findOrFail($id);

        if ($message->sender_id !== $user->id && $message->receiver_id !== $user->id) {
            abort(403);
        }

        if ($message->sender_id === $user->id) {
            $message->update(['deleted_by_sender' => true]);
        }
        
        if ($message->receiver_id === $user->id) {
            $message->update(['deleted_by_receiver' => true]);
        }

        // If both deleted, actually delete it
        if ($message->deleted_by_sender && $message->deleted_by_receiver) {
            $message->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('counselor.messages.index', $request->has('modal') ? ['modal' => 1] : [])
            ->with('success', 'Conversation deleted.');
    }
}
