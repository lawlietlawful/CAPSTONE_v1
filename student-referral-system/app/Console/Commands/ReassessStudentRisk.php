<?php

namespace App\Console\Commands;

use App\Models\RiskAssessment;
use App\Models\Student;
use App\Services\RiskAssessmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-checks risk for students whose latest assessment has gone stale (no new
 * referral or behavioral report in a while), so a long clean stretch can
 * lower an old score the same way a new incident would raise one.
 *
 * Without this, a student's risk_level is permanently sticky — it only ever
 * changes when a NEW referral/report triggers a fresh assessment, so a
 * student flagged 'high' months ago still shows 'high' today even after a
 * spotless year, despite the ML model's own days_since_last_referral feature
 * being specifically designed to let recovery show up over time.
 */
class ReassessStudentRisk extends Command
{
    protected $signature = 'students:reassess-risk
                            {--limit=100 : Maximum number of students to process}';

    protected $description = 'Re-run risk predictions for students whose last assessment is stale';

    /** Only worth re-checking once this many days have passed — anything newer already reflects current reality. */
    private const STALE_AFTER_DAYS = 14;

    public function handle(RiskAssessmentService $riskService): int
    {
        $latestIds = DB::table('risk_assessments')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('student_id')
            ->pluck('id');

        $staleStudentIds = RiskAssessment::whereIn('id', $latestIds)
            ->where('assessed_at', '<=', now()->subDays(self::STALE_AFTER_DAYS))
            ->orderBy('assessed_at')
            ->limit((int) $this->option('limit'))
            ->pluck('student_id');

        if ($staleStudentIds->isEmpty()) {
            $this->info('No stale risk assessments to refresh.');

            return self::SUCCESS;
        }

        $this->info("Found {$staleStudentIds->count()} student(s) due for a recheck.");

        $refreshed = 0;
        $failed = 0;

        foreach (Student::whereIn('id', $staleStudentIds)->get() as $student) {
            $assessment = $riskService->reassessOverTime($student);

            if ($assessment) {
                $this->line("  #{$student->id} {$student->full_name} -> {$assessment->risk_level} ({$assessment->risk_score})");
                $refreshed++;
            } else {
                // predict() returned null: the engine is down. Every
                // remaining student would fail the same way, so stop early
                // rather than hammering a dead service.
                $this->error("  #{$student->id} could not be reassessed — ML engine unreachable.");
                $failed++;
                break;
            }
        }

        $this->newLine();
        $this->info("Refreshed: {$refreshed}");

        if ($failed > 0) {
            $this->error('Stopped early — ML engine unreachable. Remaining stale students will be retried next run.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
