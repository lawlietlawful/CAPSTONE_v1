<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiskAssessment extends Model
{
    protected $fillable = [
        'student_id',
        'previous_referrals_count',
        'behavioral_reports_count',
        'concern_type_encoded',
        'days_since_last_referral',
        'risk_score',
        'risk_level',
        'risk_factors',
        'assessed_at',
    ];

    protected $casts = [
        'risk_factors' => 'array',
        'assessed_at'  => 'datetime',
    ];

    /** "Needs attention" groups: what a counselor should look at first, not just the highest score. */
    public const ATTENTION_FILTERS = [
        'safety'      => 'Safety flag',
        'rising'      => 'Rising risk',
        'stale'       => 'Not reassessed in 30+ days',
        'no_referral' => 'No open referral',
        'unassigned'  => 'Open referral, no counselor',
    ];

    /** A score up by at least this many points since the previous assessment counts as rising. */
    public const RISING_POINTS = 5;

    public const STALE_DAYS = 30;

    /**
     * Narrow an assessments query to one "needs attention" group. An unknown
     * key changes nothing. The referral-based groups only matter for
     * high/moderate students - a low-risk student with no referral is fine.
     */
    public function scopeNeedsAttention($query, string $key)
    {
        $open = fn ($r) => $r->whereIn('status', ['pending', 'in_progress']);

        match ($key) {
            // Unresolved cases whose own words name violence, a weapon or a threat (App\Support\SafetyFlags).
            'safety' => $query->whereIn('student_id', \App\Support\SafetyFlags::studentIds()),
            'rising' => $query->whereRaw(
                'risk_score >= (select prev.risk_score from risk_assessments prev'
                . ' where prev.student_id = risk_assessments.student_id and prev.id < risk_assessments.id'
                . ' order by prev.id desc limit 1) + ?',
                [self::RISING_POINTS]
            ),
            'stale' => $query->whereIn('risk_level', ['high', 'moderate'])
                ->where('assessed_at', '<=', now()->subDays(self::STALE_DAYS)),
            'no_referral' => $query->whereIn('risk_level', ['high', 'moderate'])
                ->whereHas('student', fn ($s) => $s->whereDoesntHave('referrals', $open)),
            'unassigned' => $query->whereIn('risk_level', ['high', 'moderate'])
                ->whereHas('student.referrals', fn ($r) => $open($r)->whereNull('counselor_id')),
            default => null,
        };

        return $query;
    }

    /**
     * IDs of each student's LATEST assessment (highest id) — the one
     * definition every risk view shares, so the At-Risk list, both
     * dashboards, the Students page and the seminar auto-assign job can't
     * disagree about who is at risk.
     *
     * Only ACTIVE students by default: a graduated, transferred or inactive
     * student is no longer someone the school can act on, so they don't
     * belong in the monitoring counts or the watchlist.
     *
     * With $counselorId, narrows to that counselor's own scope: students
     * assigned to them, unclaimed, or with no referral at all yet (a student
     * flagged only through behavioral reports is as unclaimed as it gets).
     */
    public static function latestIds(?int $counselorId = null, bool $activeOnly = true): Collection
    {
        $query = DB::table('risk_assessments')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('student_id');

        if ($activeOnly) {
            $query->whereIn('student_id', Student::where('status', 'active')->select('id'));
        }

        if ($counselorId !== null) {
            $query->where(function ($q) use ($counselorId) {
                $q->whereNotIn('student_id', Referral::select('student_id'))
                  ->orWhereIn('student_id', Referral::where(function ($r) use ($counselorId) {
                      $r->where('counselor_id', $counselorId)->orWhereNull('counselor_id');
                  })->select('student_id'));
            });
        }

        return $query->pluck('id');
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function referral()
    {
        return $this->hasOne(Referral::class);
    }

    // Helper — get risk level badge color
    public function getRiskColorAttribute()
    {
        return match($this->risk_level) {
            'high'     => 'red',
            'moderate' => 'yellow',
            'low'      => 'green',
            default    => 'gray',
        };
    }
}
