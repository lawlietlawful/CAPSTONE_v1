<?php

namespace App\Console\Commands;

use App\Models\BehavioralReport;
use App\Services\BehavioralReportService;
use Illuminate\Console\Command;

/**
 * Grades behavioral reports that were filed while the ML engine was unreachable.
 *
 * Such reports are stored with severity 'Unassessed' rather than a made-up 'Low'
 * (see BehavioralReportService). This command finishes the job once the engine
 * is back: it grades them, and escalates any that turn out to be serious.
 */
class ReassessBehavioralReports extends Command
{
    protected $signature = 'reports:reassess
                            {--limit=100 : Maximum number of reports to process}';

    protected $description = 'Assess behavioral reports that were filed while the ML engine was down';

    public function handle(BehavioralReportService $service): int
    {
        $pending = BehavioralReport::with(['student', 'reportedBy'])
            ->where('severity', BehavioralReportService::SEVERITY_UNASSESSED)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No unassessed behavioral reports. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$pending->count()} unassessed report(s).");

        $assessed = 0;
        $failed = 0;

        foreach ($pending as $report) {
            if ($service->reassess($report)) {
                $report->refresh();
                $escalated = $report->escalatedReferral()->exists();

                $this->line(sprintf(
                    '  #%-4d graded %-10s %s',
                    $report->id,
                    $report->severity,
                    $escalated ? '-> escalated to Guidance' : ''
                ));
                $assessed++;
            } else {
                // predict() returned null: the engine is still down. Every
                // remaining report would fail the same way, so stop early
                // rather than hammering a dead service.
                $this->error("  #{$report->id} could not be assessed — ML engine unreachable.");
                $failed++;
                break;
            }
        }

        $this->newLine();
        $this->info("Assessed: {$assessed}");

        if ($failed > 0) {
            $remaining = BehavioralReport::where('severity', BehavioralReportService::SEVERITY_UNASSESSED)->count();
            $this->error("Still unassessed: {$remaining}. Start the ML engine and re-run.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
