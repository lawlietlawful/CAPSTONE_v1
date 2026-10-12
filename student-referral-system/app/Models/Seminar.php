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

    /**
     * Whether a student is in this seminar's target audience. Blank targets
     * mean "everyone"; matching ignores case, like the database does for the
     * Assign Students list.
     */
    public function admits(Student $student): bool
    {
        return (! $this->target_course || strcasecmp((string) $student->course, $this->target_course) === 0)
            && (! $this->target_grade_level || strcasecmp((string) $student->grade_level, $this->target_grade_level) === 0);
    }

    /** "Bachelor of Science in Nursing · 1st Year", or "all students". */
    public function audienceLabel(): string
    {
        if (! $this->target_course && ! $this->target_grade_level) {
            return 'all students';
        }

        return collect([$this->target_course ?: 'all courses', $this->target_grade_level])->filter()->implode(' · ');
    }

    /**
     * Seats left, or null when there's no capacity limit. Uses a loaded
     * students_count when there is one.
     */
    public function slotsLeft(): ?int
    {
        if (! $this->max_participants) {
            return null;
        }

        return max(0, $this->max_participants - ($this->students_count ?? $this->students()->count()));
    }

    /**
     * Seminars only have a start time, so for clash checks each one is
     * treated as running this long.
     */
    public const ASSUMED_LENGTH_MINUTES = 120;

    public function startsAt(): \Carbon\Carbon
    {
        return $this->date->copy()->setTimeFromTimeString((string) $this->time);
    }

    /**
     * Other non-cancelled seminars on the same day whose (assumed) session
     * overlaps this one's — the basis for the venue and double-booking
     * warnings.
     */
    public function overlapping()
    {
        return static::whereDate('date', $this->date)
            ->where('status', '!=', 'cancelled')
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->get()
            ->filter(fn ($other) => abs($other->startsAt()->diffInMinutes($this->startsAt())) < self::ASSUMED_LENGTH_MINUTES)
            ->values();
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
