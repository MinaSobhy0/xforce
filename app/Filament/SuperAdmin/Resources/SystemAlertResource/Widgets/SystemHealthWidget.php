<?php

namespace App\Filament\SuperAdmin\Resources\SystemAlertResource\Widgets;

use App\Models\PlatformSetting;
use App\Models\SystemAlert;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemHealthWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            $this->getPostgresqlStat(),
            $this->getRedisStat(),
            $this->getQueueStat(),
            $this->getStorageStat(),
            $this->getMeilisearchStat(),
            $this->getWhatsAppStat(),
            $this->getSmsStat(),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function getPostgresqlStat(): Stat
    {
        try {
            $connections = DB::select("SELECT count(*) as count FROM pg_stat_activity WHERE state = 'active'");
            $connectionCount = $connections[0]->count ?? 0;

            return Stat::make('PostgreSQL', 'Healthy')
                ->description("Active connections: {$connectionCount}")
                ->color('success')
                ->icon('heroicon-o-circle-stack');
        } catch (\Exception $e) {
            return Stat::make('PostgreSQL', 'Error')
                ->description('Connection failed')
                ->color('danger')
                ->icon('heroicon-o-circle-stack');
        }
    }

    private function getRedisStat(): Stat
    {
        try {
            $info = Redis::info();
            $usedMemory = $info['used_memory_human'] ?? 'N/A';
            $connectedClients = $info['connected_clients'] ?? 0;

            return Stat::make('Redis', 'Healthy')
                ->description("Memory: {$usedMemory} | Clients: {$connectedClients}")
                ->color('success')
                ->icon('heroicon-o-bolt');
        } catch (\Exception $e) {
            return Stat::make('Redis', 'Disabled')
                ->description('Not configured')
                ->color('gray')
                ->icon('heroicon-o-bolt');
        }
    }

    private function getQueueStat(): Stat
    {
        $queueConnection = config('queue.default');

        if ($queueConnection === 'sync') {
            return Stat::make('Queue', 'Sync Mode')
                ->description('Jobs run immediately')
                ->color('info')
                ->icon('heroicon-o-queue-list');
        }

        // Check if Horizon is running
        try {
            $horizonStatus = \Laravel\Horizon\Contracts\MasterSupervisorRepository::class;
            if (app()->bound($horizonStatus)) {
                $masters = app($horizonStatus)->all();
                $isRunning = !empty($masters);

                return Stat::make('Queue (Horizon)', $isRunning ? 'Running' : 'Stopped')
                    ->description($isRunning ? 'Processing jobs' : 'Needs attention')
                    ->color($isRunning ? 'success' : 'danger')
                    ->icon('heroicon-o-queue-list');
            }
        } catch (\Exception $e) {
            // Horizon not installed
        }

        return Stat::make('Queue', 'Active')
            ->description("Driver: {$queueConnection}")
            ->color('success')
            ->icon('heroicon-o-queue-list');
    }

    private function getStorageStat(): Stat
    {
        $driver = PlatformSetting::get('storage_driver', 'local');

        if ($driver === 'local') {
            $totalSpace = disk_total_space(storage_path());
            $freeSpace = disk_free_space(storage_path());
            $usedSpace = $totalSpace - $freeSpace;
            $usedPercent = round(($usedSpace / $totalSpace) * 100);

            $color = $usedPercent > 90 ? 'danger' : ($usedPercent > 70 ? 'warning' : 'success');

            return Stat::make('Storage (Local)', "{$usedPercent}% Used")
                ->description($this->formatBytes($usedSpace) . ' / ' . $this->formatBytes($totalSpace))
                ->color($color)
                ->icon('heroicon-o-server-stack');
        }

        return Stat::make("Storage ({$driver})", 'Configured')
            ->description('Cloud storage active')
            ->color('success')
            ->icon('heroicon-o-cloud');
    }

    private function getMeilisearchStat(): Stat
    {
        $scoutDriver = config('scout.driver');

        if ($scoutDriver !== 'meilisearch') {
            return Stat::make('Meilisearch', 'Disabled')
                ->description('Search: ' . ($scoutDriver ?? 'none'))
                ->color('gray')
                ->icon('heroicon-o-magnifying-glass');
        }

        try {
            $client = app(\MeiliSearch\Client::class);
            $health = $client->health();

            return Stat::make('Meilisearch', 'Healthy')
                ->description('Search engine active')
                ->color('success')
                ->icon('heroicon-o-magnifying-glass');
        } catch (\Exception $e) {
            return Stat::make('Meilisearch', 'Error')
                ->description('Connection failed')
                ->color('danger')
                ->icon('heroicon-o-magnifying-glass');
        }
    }

    private function getWhatsAppStat(): Stat
    {
        $enabled = PlatformSetting::get('whatsapp_enabled', false);

        if (!$enabled) {
            return Stat::make('WhatsApp API', 'Disabled')
                ->description('Not configured')
                ->color('gray')
                ->icon('heroicon-o-chat-bubble-left-right');
        }

        $provider = PlatformSetting::get('whatsapp_provider', 'unknown');
        $apiKey = PlatformSetting::get('whatsapp_api_key');

        if (empty($apiKey)) {
            return Stat::make('WhatsApp API', 'Incomplete')
                ->description('API key missing')
                ->color('warning')
                ->icon('heroicon-o-chat-bubble-left-right');
        }

        return Stat::make('WhatsApp API', 'Configured')
            ->description("Provider: {$provider}")
            ->color('success')
            ->icon('heroicon-o-chat-bubble-left-right');
    }

    private function getSmsStat(): Stat
    {
        $enabled = PlatformSetting::get('sms_enabled', false);

        if (!$enabled) {
            return Stat::make('SMS Provider', 'Disabled')
                ->description('Not configured')
                ->color('gray')
                ->icon('heroicon-o-device-phone-mobile');
        }

        $provider = PlatformSetting::get('sms_provider', 'unknown');
        $apiKey = PlatformSetting::get('sms_api_key');

        if (empty($apiKey)) {
            return Stat::make('SMS Provider', 'Incomplete')
                ->description('API key missing')
                ->color('warning')
                ->icon('heroicon-o-device-phone-mobile');
        }

        return Stat::make('SMS Provider', 'Configured')
            ->description("Provider: {$provider}")
            ->color('success')
            ->icon('heroicon-o-device-phone-mobile');
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
