<?php

namespace Modules\Booking\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Booking\Filament\Pages\Checkout;
use Modules\Booking\Filament\Resources\VisitResource\Pages;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\Visit;

class VisitResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Visit::class;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('booking::visits.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('booking::visits.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking::visits.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label(__('booking::visits.fields.patient'))
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name . ' (' . $record->code . ')')
                            ->searchable(['first_name', 'last_name', 'phone', 'code'])
                            ->required()
                            ->disabled(),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('booking::visits.fields.branch'))
                            ->relationship('branch', 'name')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label(__('booking::visits.fields.status'))
                            ->options(Visit::STATUSES)
                            ->disabled(),

                        Forms\Components\Select::make('source')
                            ->label(__('booking::visits.fields.source'))
                            ->options(Visit::SOURCES)
                            ->disabled(),

                        Forms\Components\Textarea::make('chief_complaint')
                            ->label(__('booking::visits.fields.chief_complaint'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::visits.fields.notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::visits.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('booking::visits.fields.patient'))
                    ->searchable(['first_name', 'last_name', 'phone'])
                    ->sortable()
                    ->description(fn (Visit $record) => $record->patient?->phone),

                Tables\Columns\TextColumn::make('check_in_at')
                    ->label(__('booking::visits.fields.check_in_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_out_at')
                    ->label(__('booking::visits.fields.check_out_at'))
                    ->dateTime('H:i')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('checkedInBy.name')
                    ->label(__('booking::visits.fields.checked_in_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('appointments_count')
                    ->label(__('booking::visits.fields.appointments'))
                    ->counts('appointments')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('source')
                    ->label(__('booking::visits.fields.source'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('booking::visits.sources.' . $state) : '-')
                    ->color(fn ($state) => match ($state) {
                        'walk_in' => 'warning',
                        'appointment' => 'info',
                        'online' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('booking::visits.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('booking::visits.statuses.' . $state))
                    ->color(fn ($state) => Visit::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('booking::visits.fields.total'))
                    ->money(fn () => current_currency(), divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.code')
                    ->label(__('booking::visits.fields.invoice'))
                    ->url(fn (Visit $record) => $record->invoice_id
                        ? \Modules\Billing\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $record->invoice_id])
                        : null
                    )
                    ->color('success')
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('booking::visits.fields.status'))
                    ->options(Visit::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('source')
                    ->label(__('booking::visits.fields.source'))
                    ->options(Visit::SOURCES),

                Tables\Filters\Filter::make('check_in_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('booking::appointments.filters.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('booking::appointments.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('check_in_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('check_in_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = __('booking::appointments.filters.from') . ': ' . $data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = __('booking::appointments.filters.until') . ': ' . $data['until'];
                        }
                        return $indicators;
                    }),

                Tables\Filters\Filter::make('today')
                    ->label(__('booking::appointments.filters.today'))
                    ->query(fn (Builder $query) => $query->whereDate('check_in_at', today()))
                    ->toggle(),

                Tables\Filters\Filter::make('open_only')
                    ->label(__('booking::visits.filters.open_only'))
                    ->query(fn (Builder $query) => $query->where('status', Visit::STATUS_OPEN))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('checkout')
                    ->label(__('booking::visits.actions.checkout'))
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success')
                    ->url(fn (Visit $record) => Checkout::getUrl(['visit_id' => $record->id]))
                    ->visible(fn (Visit $record) => $record->canCheckout()),

                Tables\Actions\Action::make('view_invoice')
                    ->label(__('booking::visits.actions.view_invoice'))
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Visit $record) => $record->invoice_id
                        ? \Modules\Billing\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $record->invoice_id])
                        : null
                    )
                    ->visible(fn (Visit $record) => $record->invoice_id !== null),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for visits
            ])
            ->defaultSort('check_in_at', 'desc')
            ->poll('30s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('booking::visits.sections.visit_info'))
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label(__('booking::visits.fields.code'))
                            ->weight('bold')
                            ->color('primary')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('patient.full_name')
                            ->label(__('booking::visits.fields.patient'))
                            ->url(fn (Visit $record) => \Modules\Patients\Filament\Resources\PatientResource::getUrl('view', ['record' => $record->patient_id])),

                        Infolists\Components\TextEntry::make('status')
                            ->label(__('booking::visits.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => __('booking::visits.statuses.' . $state))
                            ->color(fn ($state) => Visit::STATUS_COLORS[$state] ?? 'gray'),

                        Infolists\Components\TextEntry::make('source')
                            ->label(__('booking::visits.fields.source'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? __('booking::visits.sources.' . $state) : '-'),

                        Infolists\Components\TextEntry::make('check_in_at')
                            ->label(__('booking::visits.fields.check_in_at'))
                            ->dateTime('M d, Y H:i'),

                        Infolists\Components\TextEntry::make('check_out_at')
                            ->label(__('booking::visits.fields.check_out_at'))
                            ->dateTime('M d, Y H:i')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('checkedInBy.name')
                            ->label(__('booking::visits.fields.checked_in_by'))
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('checkedOutBy.name')
                            ->label(__('booking::visits.fields.checked_out_by'))
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('chief_complaint')
                            ->label(__('booking::visits.fields.chief_complaint'))
                            ->columnSpanFull()
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('notes')
                            ->label(__('booking::visits.fields.notes'))
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ]),

                Infolists\Components\Section::make(__('booking::visits.sections.appointments'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('appointments')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->label(__('booking::appointments.fields.code'))
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('service.translated_name')
                                    ->label(__('booking::appointments.fields.service')),

                                Infolists\Components\TextEntry::make('practitioner.full_name')
                                    ->label(__('booking::appointments.fields.practitioner'))
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('actual_session_duration')
                                    ->label(__('booking::visits.fields.duration'))
                                    ->state(fn (Appointment $record) => $record->sessionData?->actual_duration_minutes)
                                    ->formatStateUsing(fn ($state) => $state !== null ? $state . ' min' : null)
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('status')
                                    ->label(__('booking::appointments.fields.status'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => __('booking::appointments.statuses.' . $state))
                                    ->color(fn ($state) => match ($state) {
                                        'completed' => 'success',
                                        'in_progress' => 'warning',
                                        'checked_in' => 'info',
                                        'cancelled', 'no_show' => 'danger',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('session_type')
                                    ->label(__('booking::reports.daily_visits.session_types'))
                                    ->state(function (Appointment $record) {
                                        if ($record->is_package_session) {
                                            return __('booking::reports.daily_visits.types.package');
                                        }
                                        if ($record->treatmentPlanAppointment) {
                                            $session = $record->treatmentPlanAppointment->session_number;
                                            return $session > 1
                                                ? __('booking::reports.daily_visits.types.continuation')
                                                : __('booking::reports.daily_visits.types.plan_first');
                                        }
                                        return __('booking::reports.daily_visits.types.new');
                                    })
                                    ->badge()
                                    ->color(fn (Appointment $record) => match (true) {
                                        $record->is_package_session => 'info',
                                        $record->treatmentPlanAppointment && $record->treatmentPlanAppointment->session_number > 1 => 'warning',
                                        default => 'success',
                                    }),

                                Infolists\Components\TextEntry::make('net_price')
                                    ->label(__('booking::appointments.fields.net_price'))
                                    ->money(fn () => current_currency(), divideBy: 100),
                            ])
                            ->columns(7)
                            ->contained(false),
                    ]),

                Infolists\Components\Section::make(__('booking::visits.sections.products'))
                    ->visible(fn (Visit $record) => $record->getAllSoldProducts()->isNotEmpty())
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('all_sold_products')
                            ->hiddenLabel()
                            ->state(fn (Visit $record) => $record->getAllSoldProducts())
                            ->schema([
                                Infolists\Components\TextEntry::make('product_name')
                                    ->label(__('inventory::inventory.labels.product'))
                                    ->state(fn ($record) => $record->product?->getTranslation('name', app()->getLocale()) ?? '-'),

                                Infolists\Components\TextEntry::make('quantity')
                                    ->label(__('booking::session.consumables.quantity')),

                                Infolists\Components\TextEntry::make('unit_price_minor')
                                    ->label(__('booking::session.invoice.unit_price'))
                                    ->money(fn () => current_currency(), divideBy: 100),

                                Infolists\Components\TextEntry::make('total_price_minor')
                                    ->label(__('booking::session.invoice.total'))
                                    ->money(fn () => current_currency(), divideBy: 100),
                            ])
                            ->columns(4)
                            ->contained(false),
                    ]),

                Infolists\Components\Section::make(__('booking::visits.sections.billing'))
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('services_total')
                            ->label(__('booking::session.invoice.subtotal'))
                            ->state(function (Visit $record) {
                                return $record->appointments
                                    ->where('status', Appointment::STATUS_COMPLETED)
                                    ->sum('net_price');
                            })
                            ->money(fn () => current_currency(), divideBy: 100),

                        Infolists\Components\TextEntry::make('products_total')
                            ->label(__('booking::visits.sections.products'))
                            ->state(function (Visit $record) {
                                return $record->getAllSoldProducts()->sum(function ($p) {
                                    return ($p->unit_price_minor - ($p->discount_minor ?? 0)) * $p->quantity;
                                });
                            })
                            ->money(fn () => current_currency(), divideBy: 100),

                        Infolists\Components\TextEntry::make('grand_total')
                            ->label(__('booking::session.invoice.grand_total'))
                            ->state(function (Visit $record) {
                                $services = $record->appointments
                                    ->where('status', Appointment::STATUS_COMPLETED)
                                    ->sum('net_price');
                                $products = $record->getAllSoldProducts()->sum(function ($p) {
                                    return ($p->unit_price_minor - ($p->discount_minor ?? 0)) * $p->quantity;
                                });
                                return $services + $products;
                            })
                            ->money(fn () => current_currency(), divideBy: 100)
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('invoice.code')
                            ->label(__('booking::visits.fields.invoice'))
                            ->url(fn (Visit $record) => $record->invoice_id
                                ? \Modules\Billing\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $record->invoice_id])
                                : null
                            )
                            ->color('success')
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisits::route('/'),
            'view' => Pages\ViewVisit::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'patient',
                'branch',
                'checkedInBy',
                'checkedOutBy',
                'appointments.service',
                'appointments.practitioner',
                'appointments.treatmentPlanAppointment',
                'appointments.sessionData',
                'soldProducts.product',
                'invoice',
            ]);
    }
}
