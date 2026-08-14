<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Seminar;
use App\Models\StudentSeminar;
use App\Models\RiskAssessment;
use Carbon\Carbon;

class TrackSeminarEffectiveness extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seminars:track-effectiveness';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Track risk score changes 30 days after seminar attendance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Post-Seminar Effectiveness Tracking...');

        // Find attended records where tracking hasn't been done yet, and attended_at was at least 30 days ago
        $targetDate = now()->subDays(30);

        $records = StudentSeminar::where('status', 'attended')
            ->whereNotNull('attended_at')
            ->where('attended_at', '<=', $targetDate)
            ->whereNull('effectiveness') // Only track once
            ->get();

        if ($records->isEmpty()) {
            $this->info('No eligible records found for tracking today.');
            return;
        }

        $count = 0;

        foreach ($records as $record) {
            $studentId = $record->student_id;
            
            // Get risk score on/before attendance
            $preAssessment = RiskAssessment::where('student_id', $studentId)
                ->where('assessed_at', '<=', $record->attended_at)
                ->orderBy('assessed_at', 'desc')
                ->first();

            // Get current risk score (latest)
            $postAssessment = RiskAssessment::where('student_id', $studentId)
                ->orderBy('assessed_at', 'desc')
                ->first();

            if ($preAssessment && $postAssessment) {
                $preScore = $preAssessment->risk_score;
                $postScore = $postAssessment->risk_score;
                
                $effectiveness = 'no_change';
                // Note: lower risk score means improvement!
                if ($postScore < $preScore) {
                    $effectiveness = 'improved';
                } elseif ($postScore > $preScore) {
                    $effectiveness = 'worse';
                }

                $record->update([
                    'pre_risk_score' => $preScore,
                    'post_risk_score' => $postScore,
                    'effectiveness' => $effectiveness
                ]);

                $count++;
            }
        }

        $this->info("Successfully tracked effectiveness for {$count} student records.");
    }
}
