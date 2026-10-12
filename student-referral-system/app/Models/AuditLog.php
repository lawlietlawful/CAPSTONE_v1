<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of significant actions (see the migration). Write
 * through AuditLog::record(); never edited or deleted by the app.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'user_name', 'action', 'subject_type', 'subject_id', 'description', 'ip_address'];

    protected $casts = ['created_at' => 'datetime'];

    /** Action prefix => label, for the Activity Log filter. */
    public const GROUPS = [
        'auth'         => 'Sign-ins',
        'referral'     => 'Referrals',
        'user'         => 'Accounts',
        'student'      => 'Students',
        'seminar'      => 'Seminars',
        'course'       => 'Courses',
        'intervention' => 'Interventions',
        'risk'         => 'Risk overrides',
        'settings'     => 'Settings',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an action by the signed-in user (or $actor, e.g. a failed
     * sign-in has none). $subject is the record it was done to, if any.
     */
    public static function record(string $action, ?Model $subject, string $description, ?User $actor = null): self
    {
        $actor ??= auth()->user();

        return self::create([
            'user_id'      => $actor?->id,
            'user_name'    => $actor?->name,
            'action'       => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'description'  => mb_substr($description, 0, 500),
            'ip_address'   => request()?->ip(),
        ]);
    }
}
