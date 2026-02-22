<?php

namespace Modules\Attendance\Providers;

use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Modules\Attendance\Filament\Resources\AttendanceResource;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource;
use Modules\Attendance\Filament\Resources\AttendanceViolationResource;
use Modules\Attendance\Filament\Resources\WorkingScheduleResource;
use Modules\Attendance\Filament\Widgets\TodayAttendanceWidget;

class FilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Resources are auto-discovered by Filament
    }

    public static function getResources(): array
    {
        return [
            AttendanceResource::class,
            WorkingScheduleResource::class,
            AttendanceRuleResource::class,
            AttendanceViolationResource::class,
        ];
    }

    public static function getPages(): array
    {
        return [];
    }

    public static function getWidgets(): array
    {
        return [
            TodayAttendanceWidget::class,
        ];
    }
}
