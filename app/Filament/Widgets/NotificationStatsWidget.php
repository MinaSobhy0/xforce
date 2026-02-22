<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Marketing\Models\NotificationLog;
use Illuminate\Support\Facades\DB;

class NotificationStatsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        try {
            $today = NotificationLog::today();
            $todaySent = (clone $today)->where('status', NotificationLog::STATUS_SENT)->count();
            $todayDelivered = (clone $today)->where('status', NotificationLog::STATUS_DELIVERED)->count();
            $todayFailed = (clone $today)->failed()->count();

            $thisMonth = NotificationLog::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);

            $monthlyTotal = (clone $thisMonth)->count();
            $monthlyDeliveryRate = $monthlyTotal > 0
                ? round((clone $thisMonth)->where('status', NotificationLog::STATUS_DELIVERED)->count() / $monthlyTotal * 100, 1)
                : 0;

            // Channel breakdown
            $channelStats = NotificationLog::whereDate('created_at', today())
                ->groupBy('channel')
                ->select('channel', DB::raw('COUNT(*) as count'))
                ->pluck('count', 'channel')
                ->toArray();

            $channelDescription = collect([
                'whatsapp' => $channelStats['whatsapp'] ?? 0,
                'sms' => $channelStats['sms'] ?? 0,
                'email' => $channelStats['email'] ?? 0,
            ])->filter()->map(fn ($count, $channel) => ucfirst($channel) . ": {$count}")->implode(' | ');

            return [
                Stat::make(
                    __('marketing::marketing.widgets.sent_today'),
                    $todaySent
                )
                    ->description($channelDescription ?: __('marketing::marketing.widgets.no_messages_today'))
                    ->descriptionIcon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->chart($this->getHourlyData()),

                Stat::make(
                    __('marketing::marketing.widgets.delivered_today'),
                    $todayDelivered
                )
                    ->description($todaySent > 0 ? round($todayDelivered / $todaySent * 100, 1) . '% ' . __('marketing::marketing.widgets.delivery_rate') : '-')
                    ->descriptionIcon('heroicon-m-check-badge')
                    ->color('success'),

                Stat::make(
                    __('marketing::marketing.widgets.failed_today'),
                    $todayFailed
                )
                    ->description(__('marketing::marketing.widgets.require_attention'))
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color($todayFailed > 0 ? 'danger' : 'gray'),

                Stat::make(
                    __('marketing::marketing.widgets.monthly_delivery_rate'),
                    $monthlyDeliveryRate . '%'
                )
                    ->description($monthlyTotal . ' ' . __('marketing::marketing.widgets.messages_this_month'))
                    ->descriptionIcon('heroicon-m-chart-bar')
                    ->color($monthlyDeliveryRate >= 90 ? 'success' : ($monthlyDeliveryRate >= 70 ? 'warning' : 'danger')),
            ];
        } catch (\Exception $e) {
            // Table may not exist yet
            return [];
        }
    }

    protected function getHourlyData(): array
    {
        try {
            return NotificationLog::whereDate('created_at', today())
                ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
                ->orderBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
                ->select(DB::raw('COUNT(*) as count'))
                ->pluck('count')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
