<?php

namespace Modules\Booking\Filament\Widgets;

use App\Services\BranchContext;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Modules\Booking\Filament\Pages\Checkout;
use Modules\Booking\Models\Visit;

class OpenVisitsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = null;

    public ?string $selectedDate = null;

    public function mount(?string $selectedDate = null): void
    {
        $this->selectedDate = $selectedDate ?? today()->format('Y-m-d');
    }

    #[On('dateChanged')]
    public function handleDateChange(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function getTableHeading(): string
    {
        return __('booking::reception.open_visits.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::visits.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('booking::visits.fields.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn (Visit $record) => $record->patient?->phone),

                Tables\Columns\TextColumn::make('check_in_at')
                    ->label(__('booking::visits.fields.check_in_at'))
                    ->dateTime('H:i')
                    ->description(fn (Visit $record) => $record->check_in_at?->diffForHumans()),

                Tables\Columns\TextColumn::make('appointments_count')
                    ->label(__('booking::reception.open_visits.services'))
                    ->counts('appointments')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('open_appointments')
                    ->label(__('booking::reception.open_visits.open_sessions'))
                    ->state(function (Visit $record) {
                        return $record->appointments->whereIn('status', [
                            \Modules\Booking\Models\Appointment::STATUS_CHECKED_IN,
                            \Modules\Booking\Models\Appointment::STATUS_IN_PROGRESS,
                        ])->count();
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('practitioners')
                    ->label(__('booking::reception.open_visits.doctors'))
                    ->state(function (Visit $record) {
                        return $record->appointments
                            ->pluck('practitioner.full_name')
                            ->filter()
                            ->unique()
                            ->join(', ');
                    })
                    ->wrap()
                    ->limit(30),

                Tables\Columns\TextColumn::make('estimated_total')
                    ->label(__('booking::reception.open_visits.estimated'))
                    ->state(function (Visit $record) {
                        $total = $record->appointments->sum(fn ($apt) => $apt->net_price ?? $apt->price_minor ?? 0);
                        $total += $record->products->where('usage_type', 'sold')->sum('total_price_minor');
                        return $total;
                    })
                    ->money(fn () => current_currency(), divideBy: 100)
                    ->weight('bold'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label(__('booking::visits.fields.source'))
                    ->options(Visit::SOURCES),
            ])
            ->actions([
                Tables\Actions\Action::make('checkout')
                    ->label(__('booking::visits.actions.checkout'))
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success')
                    ->url(fn (Visit $record) => Checkout::getUrl(['visit_id' => $record->id]))
                    ->visible(fn (Visit $record) => $record->canCheckout()),

                Tables\Actions\Action::make('view_patient')
                    ->label(__('booking::reception.open_visits.view_patient'))
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->url(fn (Visit $record) => \Modules\Patients\Filament\Resources\PatientResource::getUrl('view', ['record' => $record->patient_id])),
            ])
            ->emptyStateHeading(__('booking::reception.open_visits.empty'))
            ->emptyStateDescription(__('booking::reception.open_visits.empty_desc'))
            ->emptyStateIcon('heroicon-o-ticket')
            ->defaultSort('check_in_at', 'asc')
            ->poll('30s');
    }

    protected function getTableQuery(): Builder
    {
        $branchId = BranchContext::currentId();

        return Visit::query()
            ->with([
                'patient',
                'appointments.service',
                'appointments.practitioner',
                'products',
            ])
            ->where('status', Visit::STATUS_OPEN)
            ->whereDate('check_in_at', $this->selectedDate)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }
}
