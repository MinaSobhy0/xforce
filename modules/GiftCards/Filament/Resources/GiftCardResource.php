<?php

namespace Modules\GiftCards\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Models\GiftCardTemplate;
use Modules\GiftCards\Services\GiftCardService;
use Modules\Auth\Models\User;
use Modules\Patients\Models\Patient;
use Modules\Accounting\Models\Journal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class GiftCardResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = GiftCard::class;

    protected static ?string $moduleCode = 'giftcards';

    protected static ?string $permissionKey = 'gift_cards';

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('giftcards::giftcards.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('giftcards::giftcards.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('giftcards::giftcards.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('giftcards::giftcards.sections.basic_info'))
                    ->schema([
                        Forms\Components\Select::make('template_id')
                            ->label(__('giftcards::giftcards.fields.template'))
                            ->options(fn () => GiftCardTemplate::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $template = GiftCardTemplate::find($state);
                                    if ($template) {
                                        $set('initial_value_minor', $template->amount_minor / 100);
                                        if ($template->validity_days) {
                                            $set('expires_at', now()->addDays($template->validity_days));
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('initial_value_minor')
                                    ->label(__('giftcards::giftcards.fields.value'))
                                    ->required()
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                                Forms\Components\DateTimePicker::make('expires_at')
                                    ->label(__('giftcards::giftcards.fields.expires_at'))
                                    ->nullable()
                                    ->default(now()->addDays(config('giftcards.default_expiry_days', 365))),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('giftcards::giftcards.fields.notes'))
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('giftcards::giftcards.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('template.name')
                    ->label(__('giftcards::giftcards.fields.template'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('initial_value_minor')
                    ->label(__('giftcards::giftcards.fields.initial_value'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_value_minor')
                    ->label(__('giftcards::giftcards.fields.remaining_value'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->color(fn (GiftCard $record) => $record->remaining_value_minor > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('giftcards::giftcards.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => GiftCard::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => GiftCard::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('assignedToStaff.full_name')
                    ->label(__('giftcards::giftcards.fields.assigned_to'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('soldByStaff.full_name')
                    ->label(__('giftcards::giftcards.fields.sold_by'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('purchaser.full_name')
                    ->label(__('giftcards::giftcards.fields.purchaser'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('recipient.full_name')
                    ->label(__('giftcards::giftcards.fields.recipient'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('giftcards::giftcards.fields.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn (GiftCard $record) => $record->isExpiringSoon() ? 'danger' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('giftcards::giftcards.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('giftcards::giftcards.fields.status'))
                    ->options(GiftCard::STATUSES),

                Tables\Filters\SelectFilter::make('template_id')
                    ->label(__('giftcards::giftcards.filters.by_template'))
                    ->options(fn () => GiftCardTemplate::pluck('name', 'id')),

                Tables\Filters\Filter::make('has_balance')
                    ->label(__('giftcards::giftcards.filters.has_balance'))
                    ->query(fn ($query) => $query->where('remaining_value_minor', '>', 0)),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label(__('giftcards::giftcards.filters.expiring_soon'))
                    ->query(fn ($query) => $query->expiringSoon()),

                Tables\Filters\Filter::make('assigned')
                    ->label(__('giftcards::giftcards.filters.assigned'))
                    ->query(fn ($query) => $query->whereNotNull('assigned_to_staff_id')),

                Tables\Filters\Filter::make('unassigned')
                    ->label(__('giftcards::giftcards.filters.unassigned'))
                    ->query(fn ($query) => $query->whereNull('assigned_to_staff_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('activate')
                    ->label(__('giftcards::giftcards.actions.activate'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (GiftCard $record) => $record->isDraft())
                    ->modalHeading(__('giftcards::giftcards.staff_dashboard.sell_card'))
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Radio::make('patient_type')
                            ->label(__('giftcards::giftcards.staff_dashboard.patient_type'))
                            ->options([
                                'existing' => __('giftcards::giftcards.staff_dashboard.existing_patient'),
                                'new' => __('giftcards::giftcards.staff_dashboard.new_patient'),
                            ])
                            ->default('existing')
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                // Clear the other fields when switching
                                if ($state === 'new') {
                                    $set('purchaser_patient_id', null);
                                } else {
                                    $set('new_patient_first_name', null);
                                    $set('new_patient_last_name', null);
                                    $set('new_patient_phone', null);
                                    $set('new_patient_email', null);
                                }
                            }),

                        Forms\Components\Select::make('purchaser_patient_id')
                            ->label(__('giftcards::giftcards.fields.purchaser'))
                            ->options(fn () => Patient::orderBy('first_name')->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->required(fn (Forms\Get $get) => $get('patient_type') === 'existing')
                            ->visible(fn (Forms\Get $get) => $get('patient_type') === 'existing')
                            ->dehydratedWhenHidden(false),

                        Forms\Components\Section::make(__('giftcards::giftcards.staff_dashboard.new_patient'))
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('new_patient_first_name')
                                            ->label(__('patients::patients.fields.first_name'))
                                            ->required(fn (Forms\Get $get) => $get('patient_type') === 'new'),

                                        Forms\Components\TextInput::make('new_patient_last_name')
                                            ->label(__('patients::patients.fields.last_name')),
                                    ]),

                                Forms\Components\TextInput::make('new_patient_phone')
                                    ->label(__('patients::patients.fields.phone'))
                                    ->tel()
                                    ->required(fn (Forms\Get $get) => $get('patient_type') === 'new'),

                                Forms\Components\TextInput::make('new_patient_email')
                                    ->label(__('patients::patients.fields.email'))
                                    ->email(),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('patient_type') === 'new'),

                        Forms\Components\Select::make('journal_id')
                            ->label(__('giftcards::giftcards.staff_dashboard.payment_method'))
                            ->options(fn () => Journal::where('is_active', true)
                                ->whereIn('type', [Journal::TYPE_CASH, Journal::TYPE_BANK])
                                ->get()
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('recipient_patient_id')
                            ->label(__('giftcards::giftcards.fields.recipient'))
                            ->helperText(__('giftcards::giftcards.staff_dashboard.recipient_hint'))
                            ->options(fn () => Patient::orderBy('first_name')->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('giftcards::giftcards.fields.notes'))
                            ->rows(2),
                    ])
                    ->action(function (GiftCard $record, array $data) {
                        \Log::info('Gift card activation form data', [
                            'card_id' => $record->id,
                            'patient_type' => $data['patient_type'] ?? 'not set',
                            'purchaser_patient_id' => $data['purchaser_patient_id'] ?? 'not set',
                            'new_patient_first_name' => $data['new_patient_first_name'] ?? 'not set',
                        ]);

                        $purchaserData = null;

                        if (($data['patient_type'] ?? '') === 'new') {
                            // Validate new patient data
                            if (empty($data['new_patient_first_name'])) {
                                Notification::make()
                                    ->title('First name is required for new patient')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $purchaserData = [
                                'first_name' => $data['new_patient_first_name'],
                                'last_name' => $data['new_patient_last_name'] ?? '',
                                'phone' => $data['new_patient_phone'] ?? null,
                                'email' => $data['new_patient_email'] ?? null,
                            ];
                        } else {
                            // Validate existing patient selection
                            if (empty($data['purchaser_patient_id'])) {
                                Notification::make()
                                    ->title('Please select a patient')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $purchaserData = $data['purchaser_patient_id'];
                        }

                        $result = app(GiftCardService::class)->processSale(
                            $record,
                            $data['journal_id'],
                            $purchaserData,
                            $data['recipient_patient_id'] ?? null,
                            $data['notes'] ?? null
                        );

                        if (!$result['success']) {
                            Notification::make()
                                ->title($result['error'] ?? 'Failed to activate card')
                                ->danger()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title(__('giftcards::giftcards.messages.activated'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('giftcards::giftcards.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (GiftCard $record) => in_array($record->status, [GiftCard::STATUS_DRAFT, GiftCard::STATUS_ACTIVE, GiftCard::STATUS_PARTIALLY_USED]))
                    ->requiresConfirmation()
                    ->action(function (GiftCard $record) {
                        if ($record->cancel()) {
                            Notification::make()
                                ->title(__('giftcards::giftcards.messages.cancelled'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('print')
                    ->label(__('giftcards::giftcards.actions.print'))
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (GiftCard $record) => route('giftcards.print', $record->id))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assign_to_staff')
                        ->label(__('giftcards::giftcards.actions.assign_to_staff'))
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('staff_id')
                                ->label(__('giftcards::giftcards.fields.assigned_to'))
                                ->options(fn () => User::whereHas('roles', function ($query) {
                                    $query->whereIn('name', ['admin', 'receptionist', 'staff']);
                                })->get()->pluck('full_name', 'id'))
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $service = app(GiftCardService::class);
                            $count = $service->assignToStaff($records, $data['staff_id']);

                            Notification::make()
                                ->title(__('giftcards::giftcards.messages.assigned', ['count' => $count]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('unassign')
                        ->label(__('giftcards::giftcards.actions.unassign'))
                        ->icon('heroicon-o-user-minus')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->assigned_to_staff_id && $record->status === GiftCard::STATUS_DRAFT) {
                                    $record->update([
                                        'assigned_to_staff_id' => null,
                                        'assigned_at' => null,
                                    ]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title(__('giftcards::giftcards.messages.unassigned', ['count' => $count]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            \Modules\GiftCards\Filament\Resources\GiftCardResource\RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\GiftCards\Filament\Resources\GiftCardResource\Pages\ListGiftCards::route('/'),
            'create' => \Modules\GiftCards\Filament\Resources\GiftCardResource\Pages\CreateGiftCard::route('/create'),
            'view' => \Modules\GiftCards\Filament\Resources\GiftCardResource\Pages\ViewGiftCard::route('/{record}'),
        ];
    }
}
