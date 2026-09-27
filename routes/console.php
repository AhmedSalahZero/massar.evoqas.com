<?php

use Illuminate\Support\Facades\Schedule;

// ══════════════════════════════════════════════════════════════════
//  Massar — Console Routes & Schedule
//  Location: routes/console.php
//
//  Scheduled tasks. NOTE — none of this runs until the server's cron
//  calls Laravel's scheduler once a minute:
//      * * * * * cd /path/to/massar && php artisan schedule:run >> /dev/null 2>&1
//  Without that single crontab line the schedule below never runs,
//  with no error and no warning.
//
//  Commands (app/Console/Commands):
//    subscriptions:notify-expiring → partner subscription reminders
//    mail:diagnose                 → test the mail setup by hand
//    backbone:import               → load / update ISCO-08, ENOC, ESCO
//                                    (run by hand when a new edition arrives)
//    skills:import                 → load / update the ESCO skills
//                                    (run by hand, after backbone:import)
// ══════════════════════════════════════════════════════════════════

// Warn partner admins whose subscription is about to end. Early in
// the day, so they have the whole working day to act on it.
Schedule::command('subscriptions:notify-expiring')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// Talent Pool (Step 10): unfinished public registrations and CVs are not kept.
Schedule::command('pool:prune')
    ->dailyAt('03:15')
    ->withoutOverlapping();
