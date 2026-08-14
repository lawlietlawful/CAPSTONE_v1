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
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

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
    public function getDisplayReasonAttribute()
    {
        return preg_replace(
            '/^\[AUTO-ESCALATED from Behavioral Report #\d+\]\s*/',
            '',
            $this->reason
        );
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
