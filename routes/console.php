<?php

use App\Models\PlatformSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Tenant Backups - Schedule based on platform settings
Schedule::command('tenants:backup')
    ->dailyAt(PlatformSetting::get('backup_time', '02:00'))
    ->when(function () {
        $frequency = PlatformSetting::get('backup_frequency', 'daily');
        $autoBackup = PlatformSetting::get('auto_backup', true);

        if (!$autoBackup) {
            return false;
        }

        return match ($frequency) {
            'hourly' => true, // Will be overridden below
            'daily' => true,
            'weekly' => now()->dayOfWeek === 0, // Sunday
            default => true,
        };
    })
    ->withoutOverlapping()
    ->runInBackground();

// Hourly backup option
Schedule::command('tenants:backup')
    ->hourly()
    ->when(function () {
        return PlatformSetting::get('auto_backup', true)
            && PlatformSetting::get('backup_frequency') === 'hourly';
    })
    ->withoutOverlapping()
    ->runInBackground();

// Asset Depreciation - Run on the first day of each month
Schedule::command('assets:depreciate')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Auto-suspend tenants whose subscription has been past the
// `auto_suspend_after` threshold (Platform Settings → Trial, default 7).
Schedule::command('tenants:auto-suspend')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();

// Marketing Campaigns - Process scheduled campaigns every minute
Schedule::command('campaigns:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Marketing Automation - Process appointment reminders every minute
Schedule::command('automation:process-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Pull Meta WhatsApp template approval state for every connected tenant
// daily. Real-time status flips also arrive via the message_template_status_update
// webhook handler — this is the safety-net for missed deliveries.
Schedule::command('whatsapp:sync-templates')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();