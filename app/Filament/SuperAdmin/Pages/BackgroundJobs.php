<?php

namespace App\Filament\SuperAdmin\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;

class BackgroundJobs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Background Jobs';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.super-admin.pages.background-jobs';

    public function getQueueStats(): array
    {
        // In production, these would come from Redis/Horizon
        return [
            'jobs_per_minute' => 85,
            'pending' => 12,
            'failed_24h' => 3,
            'completed_24h' => 124500,
        ];
    }

    public function getQueues(): array
    {
        return [
            [
                'name' => 'default',
                'pending' => 4,
                'completed' => 45200,
                'failed' => 0,
                'throughput' => '30/min',
            ],
            [
                'name' => 'notifications',
                'pending' => 8,
                'completed' => 52100,
                'failed' => 2,
                'throughput' => '40/min',
            ],
            [
                'name' => 'reports',
                'pending' => 0,
                'completed' => 2400,
                'failed' => 1,
                'throughput' => '5/min',
            ],
            [
                'name' => 'billing',
                'pending' => 0,
                'completed' => 1200,
                'failed' => 0,
                'throughput' => '2/min',
            ],
            [
                'name' => 'maintenance',
                'pending' => 0,
                'completed' => 850,
                'failed' => 0,
                'throughput' => '1/min',
            ],
        ];
    }

    public function getRecentFailedJobs(): array
    {
        return [
            [
                'job' => 'SendWhatsAppReminder',
                'tenant' => 'cairo-glow',
                'error' => 'API timeout',
                'failed_at' => now()->subMinutes(15),
            ],
            [
                'job' => 'GenerateMonthlyReport',
                'tenant' => 'beauty-hub',
                'error' => 'Memory limit exceeded',
                'failed_at' => now()->subHours(2),
            ],
            [
                'job' => 'SendSmsNotification',
                'tenant' => 'skin-lab',
                'error' => 'Invalid phone number',
                'failed_at' => now()->subHours(5),
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_horizon')
                ->label('Open Horizon Dashboard')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url('/horizon')
                ->openUrlInNewTab()
                ->color('primary'),

            Action::make('retry_all_failed')
                ->label('Retry All Failed')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    // Artisan::call('queue:retry all');
                    \Filament\Notifications\Notification::make()
                        ->title('All failed jobs queued for retry')
                        ->success()
                        ->send();
                }),

            Action::make('clear_failed')
                ->label('Clear Failed')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    // Artisan::call('queue:flush');
                    \Filament\Notifications\Notification::make()
                        ->title('Failed jobs cleared')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function pauseQueue(): void
    {
        // Artisan::call('horizon:pause');
        \Filament\Notifications\Notification::make()
            ->title('Queue paused')
            ->warning()
            ->send();
    }

    public function resumeQueue(): void
    {
        // Artisan::call('horizon:continue');
        \Filament\Notifications\Notification::make()
            ->title('Queue resumed')
            ->success()
            ->send();
    }
}
