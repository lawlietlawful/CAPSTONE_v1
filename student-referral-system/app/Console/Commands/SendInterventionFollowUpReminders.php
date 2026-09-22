<?php

namespace App\Console\Commands;

use App\Models\Intervention;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendInterventionFollowUpReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interventions:send-followup-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify counselors about intervention follow-ups that are due today or overdue';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $this->info('Checking for due/overdue intervention follow-ups...');

        $interventions = Intervention::with(['referral.student', 'counselor'])
            ->needingFollowUpReminder()
            ->get();

        if ($interventions->isEmpty()) {
            $this->info('No follow-up reminders to send.');
            return;
        }

        foreach ($interventions as $intervention) {
            $notificationService->interventionFollowUpDue($intervention);
            $intervention->update(['follow_up_notified_at' => now()]);
        }

        $this->info("Sent {$interventions->count()} follow-up reminder(s).");
    }
}
