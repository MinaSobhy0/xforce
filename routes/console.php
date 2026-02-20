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