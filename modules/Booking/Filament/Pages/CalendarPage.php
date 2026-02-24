<?php

namespace Modules\Booking\Filament\Pages;

use Filament\Pages\Page;
use Modules\Booking\Filament\Widgets\CalendarWidget;

class CalendarPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'calendar';

    protected static string $view = 'booking::filament.pages.calendar';

    public static function getNavigationLabel(): string
    {
        return __('booking::calendar.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::calendar.title');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CalendarWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
