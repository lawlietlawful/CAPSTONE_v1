<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    /**
     * The fixed set of reasons a referral can be filed for, shown as radio/
     * select options on every referral form (Teacher, Counselor, Admin).
     * Selecting "Other" requires a free-text explanation in
     * `referral_type_other` — see getReferralTypeLabelAttribute().
     */
    public const REFERRAL_TYPES = [
        'Absences',
        'Tardiness',
        'Poor academic performance',
        'Misconduct',
        'Other',
    ];

    /**
     * Best-guess `concern_type` for a referral_type. concern_type is a broader
     * categorisation used for analytics grouping and as an ML feature, and not
     * every form asks for it directly.
     *
     * Lives on the model because BOTH the teacher-filed path (ReferralService)
     * and the auto-escalation path (BehavioralReportService) must derive it the
     * same way. The column is NOT NULL DEFAULT 'other', so a caller that forgets
     * to set it silently records 'other' instead of failing loudly.
     */
    public static function concernTypeFor(?string $referralType): string
    {
        return match ($referralType) {
            'Absences', 'Tardiness'     => 'attendance',
            'Poor academic performance' => 'academic',
            'Misconduct'                => 'behavioral',
            default                     => 'other',
        };
    }

    /**
     * Every Referrals-list filter, in one place - the Admin and Counselor
     * lists used to carry their own copies that drifted apart (different
     * searches, one missing the date range). Unknown/blank keys are ignored.
     *
     * Keys: status, priority, counselor_id (an id or 'unassigned'),
     * assignment ('mine' | 'unassigned'), date_range, search.
     *
     * @param  int|null  $me  The viewing counselor's id, for assignment=mine.
     */
    public function scopeFiltered($query, array $f, ?int $me = null)
    {
        $has = fn (string $k) => isset($f[$k]) && $f[$k] !== '' && $f[$k] !== null;

        if ($has('status')) {
            $query->where('status', $f['status']);
        }

        if ($has('priority')) {
            $query->where('priority', $f['priority']);
        }

        if ($has('counselor_id')) {
            $f['counselor_id'] === 'unassigned'
                ? $query->unassignedOpen()
                : $query->where('counselor_id', $f['counselor_id']);
        }

        if ($has('assignment')) {
            if ($f['assignment'] === 'unassigned') {
                $query->unassignedOpen();
            } elseif ($f['assignment'] === 'mine' && $me !== null) {
                $query->where('counselor_id', $me);
            }
        }

        if ($has('date_range')) {
            switch ($f['date_range']) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                    break;
                case 'last_month':
                    // NoOverflow: plain subMonth() on the 29th-31st lands in the
                    // current month (Oct 31 - 1 month = Oct 1).
                    $lastMonth = now()->subMonthNoOverflow();
                    $query->whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year);
                    break;
            }
        }

        if ($has('search')) {
            $query->whereHas('student', fn ($q) => $q->matchingSearch($f['search']));
        }

        return $query;
    }

    /** Open cases nobody owns yet. */
    public function scopeUnassignedOpen($query)
    {
        return $query->whereNull('counselor_id')->whereIn('status', ['pending', 'in_progress']);
    }

    protected $fillable = [
        'student_id',
        'referred_by',
        'counselor_id',
        'risk_assessment_id',
        'behavioral_report_id',
        'referral_type',
        'referral_type_other',
        'concern_type',
        'reason',
        'priority',
        'status',
        'counselor_notes',
        'resolved_at',
        'teacher_followup_outcome',
        'teacher_followup_note',
        'teacher_followup_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'teacher_followup_at' => 'datetime',
    ];

    /** How the referring teacher says the student is doing after resolution. */
    public const FOLLOWUP_OUTCOMES = [
        'improved'  => 'Improved',
        'no_change' => 'No change',
        'worse'     => 'Worse',
    ];

    /** Statuses where Guidance is still working on the case. */
    public const OPEN_STATUSES = ['pending', 'in_progress'];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    /**
     * Whether $user may work this case — change its status or notes,
     * reassign it, or log an intervention on it. Anyone can view any
     * referral (a shared caseload), but only the assigned counselor edits
     * it: otherwise a colleague could silently overwrite their notes or
     * take the case. Unassigned cases are open to every counselor (that's
     * how they get claimed), as is a case whose counselor has been
     * deactivated, so it never gets stranded. The super admin can always.
     */
    public function editableBy(User $user): bool
    {
        if ($user->role === 'super_admin' || ! $this->counselor_id || $this->counselor_id === $user->id) {
            return true;
        }

        return (bool) $this->counselor?->deactivated_at;
    }

    /** Query counterpart of editableBy(), for a counselor. */
    public function scopeEditableBy($query, User $user)
    {
        if ($user->role === 'super_admin') {
            return $query;
        }

        return $query->where(fn ($q) => $q->whereNull('counselor_id')
            ->orWhere('counselor_id', $user->id)
            ->orWhereHas('counselor', fn ($c) => $c->whereNotNull('deactivated_at')));
    }

    public function riskAssessment()
    {
        return $this->belongsTo(RiskAssessment::class);
    }

    /**
     * The behavioral report that auto-escalated into this referral, if any.
     * Null for referrals a teacher/counselor filed directly.
     */
    public function behavioralReport()
    {
        return $this->belongsTo(BehavioralReport::class);
    }

    public function interventions()
    {
        return $this->hasMany(Intervention::class);
    }

    public function parentCommunications()
    {
        return $this->hasMany(ParentCommunicationLog::class);
    }

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class);
    }

    /**
     * Display-friendly referral type — expands "Other" into "Other — <what
     * they typed>" when a free-text explanation was given.
     */
    public function getReferralTypeLabelAttribute()
    {
        if ($this->referral_type === 'Other' && $this->referral_type_other) {
            return 'Other — ' . $this->referral_type_other;
        }

        return $this->referral_type;
    }

    /**
     * True when this referral was created automatically by escalating a
     * behavioral report, rather than filed directly by a teacher/counselor.
     * The authoritative signal — prefer this over sniffing `reason` for the
     * "[AUTO-ESCALATED ...]" marker, which is presentation-only (see
     * getDisplayReasonAttribute()).
     */
    public function getIsAutoEscalatedAttribute()
    {
        return $this->behavioral_report_id !== null;
    }

    /**
     * `reason` with the internal "[AUTO-ESCALATED from Behavioral Report #N]"
     * marker stripped, for display. The raw column keeps the marker (it's
     * useful in logs/exports and is how older code detects auto-escalation),
     * but no UI should show it verbatim to a teacher — what follows the
     * marker is already exactly what they wrote on the original report.
     */
    /**
     * The reason as the teacher wrote it, without any system-written tags
     * ("[AUTO-ESCALATED ...]", "[Flagged: ...]"). Those explain priority to
     * counselors; the Teacher Portal shows this instead.
     */
    public function getPlainReasonAttribute(): string
    {
        return preg_replace('/^(\s*\[[^\]]*\]\s*)+/u', '', (string) $this->reason);
    }

    public function getDisplayReasonAttribute()
    {
        return preg_replace(
            '/^\[AUTO-ESCALATED from Behavioral Report #\d+\]\s*/',
            '',
            $this->reason
        );
    }

    /**
     * Why this referral is High Priority even though its own AI score isn't
     * — or null when there's nothing to explain. An auto-escalated referral
     * keeps priority 'high' by policy regardless of what the ML model scores
     * (see BehavioralReportService::maybeEscalate()), so a low/moderate AI
     * score next to a "High Priority" badge is expected, not a bug — but the
     * page showing both with no explanation looks self-contradictory. Only
     * meaningful for auto-escalated referrals: a directly-filed referral's
     * priority IS synced from its AI score, so no divergence is expected there.
     */
    public function getEscalationCaveatAttribute(): ?string
    {
        if (! $this->behavioral_report_id || $this->priority !== 'high') {
            return null;
        }

        if ($this->riskAssessment && $this->riskAssessment->risk_level === 'high') {
            return null;
        }

        if (str_contains($this->reason, '[Flagged: description names violence/a weapon/a threat]')) {
            return "Marked High Priority because the report's description named violence, a weapon, or a threat — not this AI score.";
        }

        if ($this->behavioralReport && in_array($this->behavioralReport->incident_type, \App\Services\BehavioralReportService::CRITICAL_INCIDENT_TYPES, true)) {
            return "Marked High Priority because \"{$this->behavioralReport->incident_type}\" always escalates regardless of AI score.";
        }

        return 'Marked High Priority by escalation policy, not this AI score.';
    }

    // Helper — get priority badge color
    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'high'     => 'red',
            'moderate' => 'yellow',
            'low'      => 'green',
            default    => 'gray',
        };
    }
}
