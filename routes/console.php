<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// All tenant-fan-out commands take an overlap lock so a slow run (many tenants)
// is never started a second time concurrently — preventing duplicate reminders.
// onOneServer ensures a single run across a horizontally-scaled scheduler fleet.

// Notify assignees of tasks that fall due within the next day, across active tenants.
Schedule::command('tasks:notify-due-soon')->hourly()->withoutOverlapping()->onOneServer();

// Expire sent quotations whose validity has elapsed, across active tenants.
Schedule::command('quotations:expire')->dailyAt('02:00')->withoutOverlapping()->onOneServer();

// Nightly encrypted logical backup of every tenant database.
Schedule::command('tenants:backup')->dailyAt('01:00')->withoutOverlapping()->onOneServer();
