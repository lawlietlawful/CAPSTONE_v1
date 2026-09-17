<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'subject',
        'content',
        'read_at',
        'parent_id',
        'deleted_by_sender',
        'deleted_by_receiver',
    ];

    protected $attributes = [
        'deleted_by_sender' => false,
        'deleted_by_receiver' => false,
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'deleted_by_sender' => 'boolean',
        'deleted_by_receiver' => 'boolean',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_id')->oldest();
    }

    /**
     * Whether $sender may start a new (non-reply) thread with $receiver.
     * Students never initiate — they only reply to notices. Teachers may
     * only start threads with a counselor. Counselors/admins may start
     * threads with a student or teacher. Shared by the web and API message
     * controllers so this rule lives in exactly one place.
     */
    public static function canInitiate(User $sender, User $receiver): bool
    {
        return match ($sender->role) {
            'teacher' => in_array($receiver->role, ['admin', 'super_admin'], true),
            'admin', 'super_admin' => in_array($receiver->role, ['student', 'teacher'], true),
            default => false,
        };
    }
}
