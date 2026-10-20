<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled Tasks ──

// Process queued jobs every 5 minutes (emails, notifications)
// Required for shared hosting where persistent queue workers can't run
Schedule::command('queue:work --stop-when-empty --max-time=240')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Weekly database backup to local storage and Google Drive, every Sunday at 2:00 AM
Schedule::command('db:backup')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/db-backup.log'));

// Delete abandoned CSV-import / bulk-result preview uploads older than a day
Schedule::command('storage:prune-temp')->dailyAt('03:30')->onOneServer();

// Count hosting files hourly and email an alert when nearing the cPanel inode limit
Schedule::command('health:file-usage')->hourly()->withoutOverlapping()->onOneServer();

// Reset free AI credits on the 1st of each month at midnight
Schedule::command('credits:reset-free')->monthlyOn(1, '00:00')->onOneServer();

// Send weekly digest emails to parents every Monday at 7:00 AM
Schedule::command('digest:parents-weekly')->weeklyOn(1, '07:00')->onOneServer();

// Send 24-hour deadline reminders for quizzes and exams every day at 8:00 AM
Schedule::command('reminders:send-deadlines')->dailyAt('08:00')->onOneServer();

// Archive completed academic sessions older than 1 year, every January 1st at 03:00 AM
Schedule::command('sessions:archive')->yearlyOn(1, 1, '03:00')->onOneServer();
