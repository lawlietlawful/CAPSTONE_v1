<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run the auto-assignment batch job every day at 8:00 AM
Schedule::command('seminars:auto-assign')->dailyAt('08:00');

// Run the SMS reminder batch job every day at 8:30 AM
Schedule::command('seminars:send-reminders')->dailyAt('08:30');

// Run the post-seminar effectiveness tracker daily at 1:00 AM
Schedule::command('seminars:track-effectiveness')->dailyAt('01:00');

// Roll past seminars to "completed" each night so status stays accurate.
Schedule::command('seminars:update-statuses')->dailyAt('00:05');

// Grade any behavioral report filed while the ML engine was unreachable. Runs
// hourly so an unassessed incident is picked up soon after the engine returns,
// rather than sitting ungraded until someone notices. withoutOverlapping()
// prevents a slow run from stacking on the next tick.
Schedule::command('reports:reassess')->hourly()->withoutOverlapping();

// Sanctum tokens now expire (config/sanctum.php). Sweep rows that have been
// expired for over a day so personal_access_tokens doesn't grow without bound.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Risk scores only ever move UP when a new referral/report comes in — with
// nothing to bring a stale score back down after a genuinely clean stretch,
// a student flagged 'high' once would stay flagged forever. Weekly keeps ML
// engine load light while still catching real improvement within a couple
// of weeks. withoutOverlapping() guards against a slow run (or ML engine
// downtime mid-run) stacking with the next week's tick.
Schedule::command('students:reassess-risk')->weekly()->withoutOverlapping();

// A scheduled follow-up used to have no reminder at all — a counselor only
// found out it was due/overdue if they happened to check the Interventions
// page. Runs once a day; each record is only ever notified once thanks to
// follow_up_notified_at, so this can safely run daily without spamming.
Schedule::command('interventions:send-followup-reminders')->dailyAt('07:00');
