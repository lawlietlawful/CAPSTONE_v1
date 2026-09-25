<?php

namespace App\Support;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Services\BehavioralReportService;

/**
 * "Safety flag": a student with an UNRESOLVED case whose own words name
 * violence, a weapon or a threat to harm someone.
 *
 * The behavioral-report safety net already escalates such reports to a
 * high-priority referral, but the risk views only ever showed the ML score -
 * so a student who threatened to stab a classmate could sit at "Moderate 61"
 * several rows down the At-Risk list with nothing marking them as different.
 * This makes that signal visible everywhere without touching the score.
 *
 * It uses the SAME keyword list as the escalation rule
 * (BehavioralReportService::VIOLENCE_KEYWORDS), so what the safety net treats
 * as urgent and what the screens flag can never disagree. A flag disappears
 * when the referral is resolved/cancelled and the report is resolved.
 */
class SafetyFlags
{
    /**
     * First violence/weapon/threat keyword in $text, or null. Word-boundary
     * matched like the escalation rule, after removing system-written
     * bracket tags such as "[Flagged: description names violence/a weapon/a
     * threat]" and "[AUTO-ESCALATED ...]" - those contain trigger words
     * themselves and would flag every auto-escalated case.
     */
    public static function keywordIn(?string $text): ?string
    {
        $clean = preg_replace('/^(\s*\[[^\]]*\]\s*)+/u', '', (string) $text);

        foreach (BehavioralReportService::VIOLENCE_KEYWORDS as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/iu', $clean) === 1) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Flagged students, keyed by student id:
     * [studentId => ['keyword' => 'stab', 'source' => 'referral'|'report', 'id' => 12]].
     * Open referrals are checked first, then unresolved reports; the first
     * hit per student is kept. Pass ids to limit the lookup (a page of rows).
     *
     * @param  iterable<int>|null  $studentIds  null = every student
     */
    public static function forStudents(?iterable $studentIds = null): array
    {
        $ids = $studentIds === null ? null : collect($studentIds)->unique()->values();
        if ($ids !== null && $ids->isEmpty()) {
            return [];
        }

        $flags = [];

        Referral::whereIn('status', ['pending', 'in_progress'])
            ->when($ids, fn ($q) => $q->whereIn('student_id', $ids))
            ->orderByDesc('id')
            ->get(['id', 'student_id', 'reason'])
            ->each(function ($r) use (&$flags) {
                if (! isset($flags[$r->student_id]) && ($kw = self::keywordIn($r->reason))) {
                    $flags[$r->student_id] = ['keyword' => $kw, 'source' => 'referral', 'id' => $r->id];
                }
            });

        BehavioralReport::whereIn('status', ['pending', 'reviewed'])
            ->when($ids, fn ($q) => $q->whereIn('student_id', $ids))
            ->orderByDesc('id')
            ->get(['id', 'student_id', 'description'])
            ->each(function ($r) use (&$flags) {
                if (! isset($flags[$r->student_id]) && ($kw = self::keywordIn($r->description))) {
                    $flags[$r->student_id] = ['keyword' => $kw, 'source' => 'report', 'id' => $r->id];
                }
            });

        return $flags;
    }

    /** Ids of every flagged student. */
    public static function studentIds(): array
    {
        return array_keys(self::forStudents());
    }

    /** Short tooltip text for a flag, e.g. "Open referral #82 mentions "stab"". */
    public static function describe(array $flag): string
    {
        $what = $flag['source'] === 'referral' ? 'Open referral' : 'Unresolved report';

        return "{$what} #{$flag['id']} mentions \"{$flag['keyword']}\" (violence, a weapon or a threat).";
    }
}
