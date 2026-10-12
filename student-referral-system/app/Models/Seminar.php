<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seminar extends Model
{
    protected $fillable = [
        'title',
        'description',
        'date',
        'time',
        'venue',
        'speaker',
        'is_required',
        'target_grade_level',
        'target_course',
        'max_participants',
        'status',
        'trigger_reason',
    ];

    protected $casts = [
        'date'        => 'date',
        'is_required' => 'boolean',
    ];

    /**
     * The ML trigger tags a seminar can carry, with their labels. Must match
     * the risk model's `recommended_seminar_tag` values — auto-assign, the
     * Referral page's recommendations and the Teacher Portal all match on it.
     */
    public const TRIGGER_REASONS = [
        'general'                 => 'General Guidance',
        'academic_recovery'       => 'Academic Recovery',
        'attendance_intervention' => 'Attendance Intervention',
        'behavioral_counseling'   => 'Behavioral Counseling',
        'anti_bullying'           => 'Anti-Bullying',
        'values_formation'        => 'Values Formation',
        'orientation'             => 'Orientation',
    ];

    public const STATUSES = ['upcoming', 'ongoing', 'completed', 'cancelled'];

    /** Per-student statuses on the roster (student_seminars.status). */
    public const ATTENDANCE_STATUSES = ['enrolled', 'attended', 'missed', 'excused'];

    /**
     * Flip still-"upcoming"/"ongoing" seminars dated before today to
     * "completed". The nightly seminars:update-statuses job does this, but
     * only when the scheduler runs — so the seminar pages also call it, and
     * a past seminar never sits on "Upcoming". Returns how many changed.
     */
    public static function markPastAsCompleted(): int
    {
        return static::whereIn('status', ['upcoming', 'ongoing'])
            ->whereDate('date', '<', now()->toDateString())
            ->update(['status' => 'completed']);
    }

    /** Upcoming/ongoing and not yet past — what can still be recommended or joined. */
    public function scopeStillAhead($query)
    {
        return $query->whereIn('status', ['upcoming', 'ongoing'])
            ->whereDate('date', '>=', now()->toDateString());
    }

    // Relationships
    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_seminars')
                    ->using(StudentSeminar::class)
                    ->withPivot('status', 'assigned_by', 'attended_at', 'remarks', 'pre_risk_score', 'post_risk_score', 'effectiveness')
                    ->withTimestamps();
    }

    // Helper — count attended students
    public function getTotalAttendeesAttribute()
    {
        return $this->students()->count();
    }
}
