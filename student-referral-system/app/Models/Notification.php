<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'reference_type',
        'reference_id',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read'  => 'boolean',
        'read_at'  => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper — mark as read
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * The shape the header bell's dropdown expects. Shared between the
     * initial server-rendered page (View Composer) and the JS poll endpoint
     * (Admin/Teacher NotificationController@poll) so the two can never drift
     * into disagreeing about what a notification "looks like" client-side.
     */
    public static function recentForBell(int $userId, int $limit = 4)
    {
        return static::where('user_id', $userId)
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn (self $n) => [
                'id'       => $n->id,
                'title'    => $n->title,
                'message'  => $n->message,
                'is_read'  => (bool) $n->is_read,
                'time_ago' => $n->created_at->diffForHumans(),
            ]);
    }
}
