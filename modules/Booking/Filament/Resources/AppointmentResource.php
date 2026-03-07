<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Booking\Filament\Resources\AppointmentResource\Pages;
use Modules\Booking\Filament\Resources\AppointmentResource\RelationManagers;
use Modules\Booking\Models\Appointment;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;
use Modules\Patients\Models\Patient;
use Modules\Services\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class AppointmentResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Appointment::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('booking::appointments.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::appointments.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::appointments.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make(__('booking::appointments.wizard.patient'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\Select::make('patient_id')
                                ->label(__('booking::appointments.fields.patient'))
                                ->relationship('patient', 'first_name')
                                ->getOptionLabelFromRecordUsing(fn (Patient $record) => $record->full_name . ' (' . $record->code . ')')
                                ->searchable(['first_name', 'last_name', 'phone', 'code'])
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('first_name')
                                        ->label(__('patients::patients.fields.first_name'))
                                        ->required()
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('last_name')
                                        ->label(__('patients::patients.fields.last_name'))
                                        ->required()
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('phone')
                                        ->label(__('patients::patients.fields.phone'))
                                        ->required()
                                        ->tel()
                                        ->maxLength(20),
                                    Forms\Components\TextInput::make('email')
                                        ->label(__('patients::patients.fields.email'))
                                        ->email()
                                        ->maxLength(255),
                                ])
                                ->createOptionUsing(function (array $data): string {
                                    $patient = Patient::create($data);
                                    return $patient->id;
                                }),
                        ]),

                    Forms\Components\Wizard\Step::make(__('booking::appointments.wizard.service'))
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Forms\Components\Select::make('service_id')
                                ->label(__('booking::appointments.fields.service'))
                                ->relationship('service', 'code')
                                ->getOptionLabelFromRecordUsing(fn (Service $record) => $record->translated_name)
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                    if ($state) {
                                        $service = Service::find($state);
                                        if ($service) {
                                            $set('duration_minutes', $service->duration_minutes);
                                            $set('price_minor', $service->base_price_minor);
                                        }
                                    }
                                }),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('duration_minutes')
                                        ->label(__('booking::appointments.fields.duration'))
                                        ->numeric()
                                        ->suffix(__('booking::appointments.minutes'))
                                        ->required()
                                        ->default(30),

                                    Forms\Components\TextInput::make('price_minor')
                                        ->label(__('booking::appointments.fields.price'))
                                        ->numeric()
                                        ->prefix(current_currency())
                                        ->required()
                                        ->default(0),
                                ]),

                            Forms\Components\TextInput::make('discount_minor')
                                ->label(__('booking::appointments.fields.discount'))
                                ->numeric()
                                ->prefix(current_currency())
                                ->default(0),
                        ]),

                    Forms\Components\Wizard\Step::make(__('booking::appointments.wizard.schedule'))
                        ->icon('heroicon-o-calendar')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('branch_id')
                                        ->label(__('booking::appointments.fields.branch'))
                                        ->relationship('branch', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->default(fn () => current_branch_id())
                                        ->disabled(fn () => current_branch_id() !== null)
                                        ->dehydrated()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set) {
                                            $set('practitioner_id', null);
                                            $set('room_id', null);
                                        }),

                                    Forms\Components\Select::make('practitioner_id')
                                        ->label(__('booking::appointments.fields.practitioner'))
                                        ->options(function (Forms\Get $get) {
                                            $branchId = $get('branch_id');
                                            return User::query()
                                                ->whereHas('roles', function ($q) {
                                                    $q->whereIn('name', ['doctor', 'nurse', 'technician']);
                                                })
                                                ->when($branchId, function ($q) use ($branchId) {
                                                    // Filter by branch if needed
                                                })
                                                ->pluck('first_name', 'id')
                                                ->map(fn ($name, $id) => User::find($id)?->full_name ?? $name);
                                        })
                                        ->searchable()
                                        ->required()
                                        ->live(),
                                ]),

                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\DatePicker::make('date')
                                        ->label(__('booking::appointments.fields.date'))
                                        ->native(false)
                                        ->required()
                                        ->minDate(today())
                                        ->live(),

                                    Forms\Components\TimePicker::make('start_time')
                                        ->label(__('booking::appointments.fields.start_time'))
                                        ->seconds(false)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                                            if ($state && $get('duration_minutes')) {
                                                $start = \Carbon\Carbon::parse($state);
                                                $end = $start->copy()->addMinutes((int) $get('duration_minutes'));
                                                $set('end_time', $end->format('H:i'));
                                            }
                                        }),

                                    Forms\Components\TimePicker::make('end_time')
                                        ->label(__('booking::appointments.fields.end_time'))
                                        ->seconds(false)
                                        ->required(),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('room_id')
                                        ->label(__('booking::appointments.fields.room'))
                                        ->relationship(
                                            'room',
                                            'name',
                                            fn (Builder $query, Forms\Get $get) => $query->where('branch_id', $get('branch_id'))
                                        )
                                        ->searchable()
                                        ->preload(),

                                    Forms\Components\Select::make('source')
                                        ->label(__('booking::appointments.fields.source'))
                                        ->options(Appointment::SOURCES)
                                        ->default(Appointment::SOURCE_PHONE)
                                        ->required(),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make(__('booking::appointments.wizard.confirm'))
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Forms\Components\Textarea::make('notes')
                                ->label(__('booking::appointments.fields.notes'))
                                ->rows(3)
                                ->maxLength(1000),

                            Forms\Components\Textarea::make('internal_notes')
                                ->label(__('booking::appointments.fields.internal_notes'))
                                ->rows(2)
                                ->maxLength(1000)
                                ->helperText(__('booking::appointments.fields.internal_notes_help')),
                        ]),
                ])
                ->columnSpanFull()
                ->skippable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::appointments.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('booking::appointments.fields.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('service.translated_name')
                    ->label(__('booking::appointments.fields.service'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('practitioner.full_name')
                    ->label(__('booking::appointments.fields.practitioner'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('booking::appointments.fields.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_time')
                    ->label(__('booking::appointments.fields.time'))
                    ->sortable(['start_time']),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('booking::appointments.fields.status'))
                    ->colors([
                        'info' => Appointment::STATUS_SCHEDULED,
                        'primary' => Appointment::STATUS_CONFIRMED,
                        'warning' => Appointment::STATUS_CHECKED_IN,
                        'secondary' => Appointment::STATUS_IN_PROGRESS,
                        'success' => Appointment::STATUS_COMPLETED,
                        'danger' => Appointment::STATUS_CANCELLED,
                        'gray' => fn ($state) => in_array($state, [Appointment::STATUS_NO_SHOW, Appointment::STATUS_RESCHEDULED]),
                    ])
                    ->formatStateUsing(fn (string $state): string => Appointment::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::appointments.fields.branch'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('source')
                    ->label(__('booking::appointments.fields.source'))
                    ->formatStateUsing(fn (string $state): string => Appointment::SOURCES[$state] ?? $state)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('booking::appointments.fields.status'))
                    ->options(Appointment::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('booking::appointments.fields.branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('practitioner_id')
                    ->label(__('booking::appointments.fields.practitioner'))
                    ->relationship('practitioner', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('service_id')
                    ->label(__('booking::appointments.fields.service'))
                    ->relationship('service', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Service $record) => $record->translated_name)
                    ->preload()
                    ->searchable(),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('booking::appointments.filters.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('booking::appointments.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('date', '<=', $date));
                    }),

                Tables\Filters\Filter::make('today')
                    ->label(__('booking::appointments.filters.today'))
                    ->query(fn (Builder $query): Builder => $query->today())
                    ->toggle(),

                Tables\Filters\Filter::make('upcoming')
                    ->label(__('booking::appointments.filters.upcoming'))
                    ->query(fn (Builder $query): Builder => $query->upcoming())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('confirm')
                        ->label(__('booking::appointments.actions.confirm'))
                        ->icon('heroicon-o-check')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->visible(fn (Appointment $record): bool => $record->canTransitionTo(Appointment::STATUS_CONFIRMED))
                        ->action(fn (Appointment $record) => $record->confirm()),

                    Tables\Actions\Action::make('check_in')
                        ->label(__('booking::appointments.actions.check_in'))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Appointment $record): bool => $record->canTransitionTo(Appointment::STATUS_CHECKED_IN))
                        ->action(function (Appointment $record) {
                            $record->checkIn();

                            // Check if this is a package session with unpaid balance
                            if ($record->isPackageSession() && $record->packageSubscription) {
                                $subscription = $record->packageSubscription;

                                if ($subscription->hasBalance()) {
                                    $packageName = $subscription->package?->getTranslation('name', app()->getLocale()) ?? 'Package';
                                    $balance = format_money($subscription->balance_remaining_minor);
                                    $patientName = $record->patient?->full_name ?? 'Patient';

                                    Notification::make()
                                        ->title(__('booking::appointments.notifications.package_balance_due'))
                                        ->body(__('booking::appointments.notifications.package_balance_message', [
                                            'patient' => $patientName,
                                            'package' => $packageName,
                                            'balance' => $balance,
                                        ]))
                                        ->warning()
                                        ->persistent()
                                        ->actions([
                                            NotificationAction::make('pay')
                                                ->label(__('booking::appointments.actions.pay_balance'))
                                                ->url(route('filament.tenant.resources.package-subscriptions.view', $subscription->id))
                                                ->button()
                                                ->color('success'),
                                            NotificationAction::make('dismiss')
                                                ->label(__('booking::appointments.actions.dismiss'))
                                                ->close(),
                                        ])
                                        ->send();

                                    return;
                                }
                            }

                            Notification::make()
                                ->title(__('booking::appointments.messages.checked_in'))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('cancel')
                        ->label(__('booking::appointments.actions.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label(__('booking::appointments.fields.cancellation_reason'))
                                ->required(),
                        ])
                        ->visible(fn (Appointment $record): bool => $record->canTransitionTo(Appointment::STATUS_CANCELLED))
                        ->action(fn (Appointment $record, array $data) => $record->cancel($data['cancellation_reason'])),

                    Tables\Actions\Action::make('no_show')
                        ->label(__('booking::appointments.actions.no_show'))
                        ->icon('heroicon-o-user-minus')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (Appointment $record): bool => $record->canTransitionTo(Appointment::STATUS_NO_SHOW))
                        ->action(fn (Appointment $record) => $record->markNoShow()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('booking::appointments.sections.details'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->label(__('booking::appointments.fields.code')),

                                Infolists\Components\TextEntry::make('status')
                                    ->label(__('booking::appointments.fields.status'))
                                    ->badge()
                                    ->color(fn (Appointment $record): string => $record->status_color),

                                Infolists\Components\TextEntry::make('source')
                                    ->label(__('booking::appointments.fields.source'))
                                    ->formatStateUsing(fn (string $state): string => Appointment::SOURCES[$state] ?? $state),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('booking::appointments.sections.patient'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('patient.full_name')
                                    ->label(__('booking::appointments.fields.patient')),

                                Infolists\Components\TextEntry::make('patient.phone')
                                    ->label(__('patients::patients.fields.phone')),

                                Infolists\Components\TextEntry::make('patient.balance_minor')
                                    ->label(__('patients::patients.balance.title'))
                                    ->formatStateUsing(fn ($state) => $state != 0 ? number_format(abs($state) / 100, 2) . ' ' . current_currency() : '-')
                                    ->badge()
                                    ->color(fn (Appointment $record): string => $record->patient?->balance_status_color ?? 'gray')
                                    ->suffix(fn (Appointment $record) => match(true) {
                                        ($record->patient?->balance_minor ?? 0) > 0 => ' ' . __('patients::patients.balance.owes'),
                                        ($record->patient?->balance_minor ?? 0) < 0 => ' ' . __('patients::patients.balance.credit'),
                                        default => '',
                                    })
                                    ->visible(fn (Appointment $record) => ($record->patient?->balance_minor ?? 0) != 0),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('booking::appointments.sections.schedule'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('date')
                                    ->label(__('booking::appointments.fields.date'))
                                    ->date(),

                                Infolists\Components\TextEntry::make('formatted_time')
                                    ->label(__('booking::appointments.fields.time')),

                                Infolists\Components\TextEntry::make('duration_minutes')
                                    ->label(__('booking::appointments.fields.duration'))
                                    ->suffix(' ' . __('booking::appointments.minutes')),

                                Infolists\Components\TextEntry::make('branch.name')
                                    ->label(__('booking::appointments.fields.branch')),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('practitioner.full_name')
                                    ->label(__('booking::appointments.fields.practitioner')),

                                Infolists\Components\TextEntry::make('room.name')
                                    ->label(__('booking::appointments.fields.room'))
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('service.translated_name')
                                    ->label(__('booking::appointments.fields.service')),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('booking::appointments.sections.pricing'))
                    ->schema([
                        Infolists\Components\Grid::make(5)
                            ->schema([
                                Infolists\Components\TextEntry::make('price_minor')
                                    ->label(__('booking::appointments.fields.price'))
                                    ->money(current_currency(), divideBy: 100),

                                Infolists\Components\TextEntry::make('discount_minor')
                                    ->label(__('booking::appointments.fields.discount'))
                                    ->money(current_currency(), divideBy: 100),

                                Infolists\Components\TextEntry::make('net_price')
                                    ->label(__('booking::appointments.fields.net_price'))
                                    ->money(current_currency(), divideBy: 100),

                                Infolists\Components\TextEntry::make('paid_amount')
                                    ->label(__('billing::billing.fields.paid'))
                                    ->state(fn (Appointment $record) => \Modules\Billing\Models\Payment::forAppointment($record->id)->completed()->sum('amount_minor'))
                                    ->money(current_currency(), divideBy: 100)
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('remaining_amount')
                                    ->label(__('billing::billing.fields.remaining'))
                                    ->state(function (Appointment $record) {
                                        $paid = \Modules\Billing\Models\Payment::forAppointment($record->id)->completed()->sum('amount_minor');
                                        return max(0, ($record->net_price ?? 0) - $paid);
                                    })
                                    ->money(current_currency(), divideBy: 100)
                                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('booking::appointments.sections.invoice'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('effective_invoice_code')
                                    ->label(__('billing::billing.fields.invoice_code'))
                                    ->state(fn (Appointment $record) => $record->effective_invoice?->code)
                                    ->placeholder('-')
                                    ->url(fn (Appointment $record) => $record->effective_invoice
                                        ? route('filament.tenant.resources.invoices.view', ['record' => $record->effective_invoice->id])
                                        : null),

                                Infolists\Components\TextEntry::make('effective_invoice_status')
                                    ->label(__('billing::billing.fields.status'))
                                    ->state(fn (Appointment $record) => $record->effective_invoice?->status)
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state ? __('billing::billing.statuses.' . $state) : '-')
                                    ->color(fn (Appointment $record): string => $record->effective_invoice?->status_color ?? 'gray')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('effective_invoice_total')
                                    ->label(__('billing::billing.fields.total'))
                                    ->state(fn (Appointment $record) => $record->effective_invoice?->total_minor)
                                    ->money(current_currency(), divideBy: 100)
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('effective_invoice_paid')
                                    ->label(__('billing::billing.fields.paid'))
                                    ->state(fn (Appointment $record) => $record->effective_invoice?->paid_minor)
                                    ->money(current_currency(), divideBy: 100)
                                    ->color('success')
                                    ->placeholder('-'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('effective_invoice_remaining')
                                    ->label(__('billing::billing.fields.remaining'))
                                    ->state(fn (Appointment $record) => $record->effective_invoice
                                        ? max(0, $record->effective_invoice->total_minor - $record->effective_invoice->paid_minor)
                                        : null)
                                    ->money(current_currency(), divideBy: 100)
                                    ->color(fn (Appointment $record) => ($record->effective_invoice && ($record->effective_invoice->total_minor - $record->effective_invoice->paid_minor) > 0) ? 'danger' : 'success')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('effective_invoice_issued_at')
                                    ->label(__('billing::billing.fields.issued_at'))
                                    ->state(fn (Appointment $record) => $record->effective_invoice?->issued_at)
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->visible(fn (Appointment $record): bool => $record->effective_invoice !== null)
                    ->collapsible(),

                Infolists\Components\Section::make(__('booking::appointments.sections.visit'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('current_visit.code')
                                    ->label(__('booking::appointments.fields.visit_code'))
                                    ->placeholder('-')
                                    ->url(fn (Appointment $record) => $record->current_visit
                                        ? route('filament.tenant.resources.visits.view', ['record' => $record->current_visit->id])
                                        : null),

                                Infolists\Components\TextEntry::make('current_visit.status')
                                    ->label(__('booking::appointments.fields.visit_status'))
                                    ->badge()
                                    ->color(fn (Appointment $record): string => $record->current_visit?->status_color ?? 'gray')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('current_visit.check_in_at')
                                    ->label(__('booking::appointments.fields.visit_check_in'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('current_visit.checked_in_by_name')
                                    ->label(__('booking::appointments.fields.checked_in_by'))
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->visible(fn (Appointment $record): bool => $record->current_visit !== null)
                    ->collapsible(),

                Infolists\Components\Section::make(__('booking::appointments.sections.notes'))
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label(__('booking::appointments.fields.notes'))
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('internal_notes')
                            ->label(__('booking::appointments.fields.internal_notes'))
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('cancellation_reason')
                            ->label(__('booking::appointments.fields.cancellation_reason'))
                            ->placeholder('-')
                            ->visible(fn (Appointment $record): bool => $record->isCancelled()),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make(__('booking::appointments.sections.timestamps'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('confirmed_at')
                                    ->label(__('booking::appointments.fields.confirmed_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('checked_in_at')
                                    ->label(__('booking::appointments.fields.checked_in_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('started_at')
                                    ->label(__('booking::appointments.fields.started_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('completed_at')
                                    ->label(__('booking::appointments.fields.completed_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('cancelled_at')
                                    ->label(__('booking::appointments.fields.cancelled_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label(__('booking::appointments.fields.created_at'))
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ServiceNoteRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            // Create redirects to /admin/create-booking page
            'view' => Pages\ViewAppointment::route('/{record}'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['patient', 'service', 'practitioner', 'branch', 'room', 'invoice', 'packageSubscription.invoice']);
    }
}
