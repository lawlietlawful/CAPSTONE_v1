<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Seminar;
use App\Models\RiskAssessment;
use App\Models\StudentSeminar;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Services\SmsService;

class AutoAssignSeminars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seminars:auto-assign';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batch assigns high and moderate risk students to upcoming seminars 3 days before the seminar date.';

    /**
     * Execute the console command.
     */
    public function handle(SmsService $smsService)
    {
        $this->info('Starting batch assignment for seminars 3 to 5 days from now...');

        $startDate = Carbon::now()->addDays(3)->toDateString();
        $endDate = Carbon::now()->addDays(5)->toDateString();
        
        $seminars = Seminar::whereBetween('date', [$startDate, $endDate])->where('status', 'upcoming')->get();

        if ($seminars->isEmpty()) {
            $this->info("No upcoming seminars scheduled between $startDate and $endDate.");
            return;
        }

        foreach ($seminars as $seminar) {
            $this->info("Processing Seminar: {$seminar->title} (Trigger: {$seminar->trigger_reason})");

            $trigger = $seminar->trigger_reason ?? 'general';

            // Find the latest risk assessment for each student
            // Active students only: enrolling a graduated or transferred student in a seminar is wasted.
            $latestRiskIds = RiskAssessment::latestIds();

            // Find high and moderate risk among those latest assessments
            $targetAssessments = RiskAssessment::with('student')
                ->whereIn('id', $latestRiskIds)
                ->whereIn('risk_level', ['moderate', 'high'])
                ->get()
                ->filter(function ($assessment) use ($trigger) {
                    // risk_factors is cast to 'array' on the model — already
                    // decoded here, no json_decode needed.
                    $factors = $assessment->risk_factors ?? [];
                    $recTag = $factors['recommended_seminar_tag'] ?? 'general';
                    return $recTag === $trigger;
                });

            $assignedCount = 0;

            foreach ($targetAssessments as $assessment) {
                $student = $assessment->student;
                if (!$student) continue;

                // Check max participants if seminar has a limit
                if ($seminar->max_participants > 0 && $seminar->students()->count() >= $seminar->max_participants) {
                    $this->warn("Seminar capacity reached for {$seminar->title}. Skipping remaining students.");
                    break;
                }

                $exists = StudentSeminar::where('student_id', $student->id)
                                        ->where('seminar_id', $seminar->id)
                                        ->exists();

                if (!$exists) {
                    StudentSeminar::create([
                        'student_id' => $student->id,
                        'seminar_id' => $seminar->id,
                        'status' => 'enrolled',
                        'assigned_by' => 'ml_system'
                    ]);
                    $assignedCount++;

                    // Trigger SMS to Parent about Seminar Assignment
                    if (!empty($student->parent_contact)) {
                        $date = Carbon::parse($seminar->date)->format('M d, Y');
                        $time = Carbon::parse($seminar->time)->format('h:i A');
                        $msg = "MU Guidance: Your child {$student->first_name} has been auto-enrolled in '{$seminar->title}' on {$date} at {$time}, {$seminar->venue}. Attendance is required.";
                        
                        try {
                            $smsService->sendSms(
                                $student->parent_contact,
                                $msg,
                                $student->id,
                                $student->parent_name ?? 'Parent',
                                'parent'
                            );
                        } catch (\Exception $e) {
                            Log::error("Failed to send auto-assign SMS to {$student->parent_contact}: " . $e->getMessage());
                        }
                    }
                }
            }

            $this->info("Assigned {$assignedCount} new students to '{$seminar->title}'.");
        }

        $this->info('Batch assignment completed successfully.');
    }
}
