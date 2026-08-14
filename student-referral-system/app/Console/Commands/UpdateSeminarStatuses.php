<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Seminar;

class UpdateSeminarStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seminars:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark seminars whose scheduled date has passed as completed, so status stays accurate without manual editing.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating seminar statuses...');

        // Any still-"upcoming"/"ongoing" seminar dated before today has clearly
        // already happened — flip it to "completed". Cancelled and already
        // completed seminars are left untouched.
        $completed = Seminar::whereIn('status', ['upcoming', 'ongoing'])
            ->whereDate('date', '<', now()->toDateString())
            ->update(['status' => 'completed']);

        $this->info("Marked {$completed} past seminar(s) as completed.");

        return self::SUCCESS;
    }
}
