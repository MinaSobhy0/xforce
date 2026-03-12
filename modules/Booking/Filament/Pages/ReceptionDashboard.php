<?php

namespace Modules\Booking\Filament\Pages;

use App\Services\BranchContext;
use App\Traits\ChecksResourcePermissions;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Modules\Booking\Services\ReceptionService;

class ReceptionDashboard extends Page implements HasForms
{
    use ChecksResourcePermissions;
    use InteractsWithForms;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'reception_dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reception';

    protected static string $view = 'booking::filament.pages.reception-dashboard';

    #[Url]
    public ?string $selectedDate = null;

    public function mount(): void
    {
        $this->selectedDate = $this->selectedDate ?? today()->format('Y-m-d');
    }

    public static function getNavigationLabel(): string
    {
        return __('booking::reception.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::reception.title');
    }

    public function getHeading(): string
    {
        $date = Carbon::parse($this->selectedDate);
        return __('booking::reception.heading') . ' - ' . $date->format('l, M d, Y');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('selectedDate')
                    ->label(__('booking::reception.filters.date'))
                    ->native(false)
                    ->displayFormat('D, M d, Y')
                    ->closeOnDateSelection()
                    ->live()
                    ->afterStateUpdated(fn () => $this->dispatch('dateChanged', date: $this->selectedDate)),
            ])
            ->statePath('data');
    }

    public function updatedSelectedDate(): void
    {
        $this->dispatch('dateChanged', date: $this->selectedDate);
    }

    public function goToToday(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
        $this->dispatch('dateChanged', date: $this->selectedDate);
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->format('Y-m-d');
        $this->dispatch('dateChanged', date: $this->selectedDate);
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->format('Y-m-d');
        $this->dispatch('dateChanged', date: $this->selectedDate);
    }

    public function getSelectedDateProperty(): Carbon
    {
        return Carbon::parse($this->selectedDate);
    }

    public function isToday(): bool
    {
        return Carbon::parse($this->selectedDate)->isToday();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \Modules\Booking\Filament\Widgets\ReceptionStatsWidget::make([
                'selectedDate' => $this->selectedDate,
            ]),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->hasAnyRole([
            'receptionist',
            'admin',
            'manager',
            'super-admin',
            'super_admin',
            'owner',
            'tenant-owner',
            'tenant_owner',
            'doctor',
            'nurse',
        ]);
    }
}
