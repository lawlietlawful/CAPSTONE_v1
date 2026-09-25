<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    use HasFactory;

    public const TYPES = [
        'One-on-One Counseling',
        'Group Counseling',
        'Parent-Teacher Conference',
        'Disciplinary Warning',
        'Academic Coaching',
        'Behavioral Contract',
        'Psychological First Aid',
    ];

    protected $fillable = [
        'referral_id',
        'counselor_id',
        'intervention_type',
        'description',
        'intervention_date',
        'outcome',
        'follow_up_notes',
        'follow_up_date',
        'follow_up_notified_at',
    ];

    protected $casts = [
        'intervention_date'     => 'date',
        'follow_up_date'        => 'date',
        'follow_up_notified_at' => 'datetime',
    ];

    // Relationships
    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    /**
     * A scheduled follow-up that's already past and was never marked
     * resolved — nothing else in the app currently flags these, so a
     * counselor has no way to tell which ones need a follow-up session
     * without checking every record by hand.
     */
    public function getIsFollowUpOverdueAttribute(): bool
    {
        // follow_up_date has no time component (cast to 'date', midnight) —
        // compare against the start of today so a follow-up due TODAY isn't
        // flagged as overdue a few hours early.
        return $this->follow_up_date !== null
            && $this->follow_up_date->lt(now()->startOfDay())
            && $this->outcome !== 'resolved';
    }

    /**
     * Follow-ups that still call for action — what the dashboards list.
     * A follow-up is ignored when its case is closed (referral resolved or
     * cancelled), when the session itself was marked resolved, or when a
     * NEWER session on the same referral has superseded it (the counselor
     * followed up; the old due date never changes, but it no longer counts).
     */
    public function scopeActiveFollowUp($query)
    {
        return $query->whereNotNull('interventions.follow_up_date')
            ->where(function ($q) {
                $q->whereNull('interventions.outcome')->orWhere('interventions.outcome', '!=', 'resolved');
            })
            ->whereHas('referral', fn ($r) => $r->whereIn('status', ['pending', 'in_progress']))
            ->whereIn('interventions.id', function ($sub) {
                $sub->from('interventions as newest')
                    ->selectRaw('MAX(newest.id)')
                    ->groupBy('newest.referral_id');
            });
    }

    public function scopeOverdueFollowUp($query)
    {
        return $query->whereNotNull('follow_up_date')
            ->where('follow_up_date', '<', now()->startOfDay())
            ->where(function ($q) {
                $q->whereNull('outcome')->orWhere('outcome', '!=', 'resolved');
            });
    }

    /**
     * Due today or already overdue, and not yet reminded about — used by the
     * daily reminder job. Deliberately separate from overdueFollowUp(): that
     * one drives the UI (excludes today, since "due today" isn't overdue
     * yet), this one drives notifications (includes today, since a reminder
     * on the due date itself is exactly the point) and is one-shot via
     * follow_up_notified_at so the same record never gets reminded twice.
     */
    public function scopeNeedingFollowUpReminder($query)
    {
        return $query->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', today())
            ->whereNull('follow_up_notified_at')
            ->where(function ($q) {
                $q->whereNull('outcome')->orWhere('outcome', '!=', 'resolved');
            });
    }
}
