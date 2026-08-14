<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Seminar;
use App\Services\SmsService;

class SendSeminarReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seminars:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 24-hour SMS reminders to students and parents for upcoming seminars';

    /**
     * Execute the console command.
     */
    public function handle(SmsService $smsService)
    {
        $this->info('Starting 24-hour seminar reminder job...');

        // Find seminars exactly 1 day from now
        $targetDate = now()->addDay()->format('Y-m-d');
        
        $seminars = Seminar::where('status', 'upcoming')
            ->whereDate('date', $targetDate)
            ->with(['students' => function($q) {
                // Only remind students still expected to attend (not those who
                // already attended, missed, or were excused). Uses wherePivotIn
                // so Eloquent resolves the correct pivot table name itself.
                $q->wherePivotIn('status', ['enrolled']);
            }])
            ->get();

        if ($seminars->isEmpty()) {
            $this->info("No upcoming seminars scheduled for {$targetDate}.");
            return;
        }

        $count = 0;

        foreach ($seminars as $seminar) {
            $time = \Carbon\Carbon::parse($seminar->time)->format('h:i A');
            $this->info("Processing seminar: {$seminar->title} at {$time}");

            foreach ($seminar->students as $student) {
                // Prepare messages
                $msgParent = "REMINDER: Your child {$student->first_name} has a scheduled seminar '{$seminar->title}' TOMORROW at {$time}, {$seminar->venue}. Please ensure they attend. - MU Guidance Office";
                $msgStudent = "REMINDER: You have a scheduled seminar '{$seminar->title}' TOMORROW at {$time}, {$seminar->venue}. Attendance is required. - MU Guidance Office";

                // Send to parent
                if (!empty($student->parent_contact)) {
                    $smsService->sendSms($student->parent_contact, $msgParent, $student->id, $student->parent_name ?? 'Parent', 'parent');
                }

                // Send to student
                if (!empty($student->student_contact)) {
                    $smsService->sendSms($student->student_contact, $msgStudent, $student->id, $student->first_name . ' ' . $student->last_name, 'student');
                }

                $count++;
            }
        }

        $this->info("Reminder job complete. Sent reminders for {$count} students.");
    }
}
