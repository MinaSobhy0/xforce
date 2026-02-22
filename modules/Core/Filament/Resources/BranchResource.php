<?php

namespace Modules\Core\Filament\Resources;

use Modules\Core\Filament\Resources\BranchResource\Pages;
use Modules\Core\Filament\Resources\BranchResource\RelationManagers;
use Modules\Core\Filament\Resources\BranchResource\Widgets;
use Modules\Core\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('core::core.branches');
    }

    public static function getModelLabel(): string
    {
        return __('core::core.branch');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::core.branches');
    }

    /**
     * Check if tenant can create more branches.
     */
    public static function canCreate(): bool
    {
        $tenant = app('currentTenant');

        if (!$tenant) {
            return true; // Allow if no tenant context (shouldn't happen)
        }

        $currentBranchCount = Branch::count();

        // Get effective limit from plan + extra purchased
        $planLimit = $tenant->plan?->max_branches ?? 0;
        $extraBranches = $tenant->extra_branches ?? 0;
        $maxBranches = $planLimit + $extraBranches;

        // If no limit set (0), allow unlimited
        if ($maxBranches <= 0) {
            return true;
        }

        return $currentBranchCount < $maxBranches;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('core::core.branch_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('core::core.name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('code')
                            ->label(__('core::core.code'))
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_main')
                            ->label(__('core::core.main_branch'))
                            ->helperText(__('core::core.main_branch_help'))
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('core::core.contact_information'))
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->label(__('core::core.address'))
                            ->rows(2)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('city')
                            ->label(__('core::core.city'))
                            ->maxLength(100)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('phone')
                            ->label(__('core::core.phone'))
                            ->tel()
                            ->maxLength(20)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('email')
                            ->label(__('core::core.email'))
                            ->email()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('google_maps_url')
                            ->label(__('core::core.google_maps_url'))
                            ->url()
                            ->maxLength(500)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('core::core.working_hours'))
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('quick_setup_standard')
                                ->label('Standard (9 AM - 6 PM, Fri Off)')
                                ->icon('heroicon-o-clock')
                                ->color('primary')
                                ->action(function (Forms\Set $set) {
                                    $set('working_hours', static::getStandardHours());
                                }),
                            Forms\Components\Actions\Action::make('quick_setup_extended')
                                ->label('Extended (8 AM - 10 PM)')
                                ->icon('heroicon-o-sun')
                                ->color('success')
                                ->action(function (Forms\Set $set) {
                                    $set('working_hours', static::getExtendedHours());
                                }),
                            Forms\Components\Actions\Action::make('quick_setup_clinic')
                                ->label('Clinic (10 AM - 8 PM, Fri Off)')
                                ->icon('heroicon-o-heart')
                                ->color('info')
                                ->action(function (Forms\Set $set) {
                                    $set('working_hours', static::getClinicHours());
                                }),
                            Forms\Components\Actions\Action::make('quick_setup_24h')
                                ->label('24/7')
                                ->icon('heroicon-o-building-office')
                                ->color('warning')
                                ->action(function (Forms\Set $set) {
                                    $set('working_hours', static::get24Hours());
                                }),
                        ])->columnSpanFull(),

                        Forms\Components\Repeater::make('working_hours')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->label(__('core::core.day'))
                                    ->options([
                                        'sunday' => __('core::core.days_of_week.sunday'),
                                        'monday' => __('core::core.days_of_week.monday'),
                                        'tuesday' => __('core::core.days_of_week.tuesday'),
                                        'wednesday' => __('core::core.days_of_week.wednesday'),
                                        'thursday' => __('core::core.days_of_week.thursday'),
                                        'friday' => __('core::core.days_of_week.friday'),
                                        'saturday' => __('core::core.days_of_week.saturday'),
                                    ])
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TimePicker::make('open_time')
                                    ->label(__('core::core.open_time'))
                                    ->seconds(false)
                                    ->columnSpan(1),

                                Forms\Components\TimePicker::make('close_time')
                                    ->label(__('core::core.close_time'))
                                    ->seconds(false)
                                    ->columnSpan(1),

                                Forms\Components\Toggle::make('is_closed')
                                    ->label(__('core::core.closed'))
                                    ->columnSpan(1),
                            ])
                            ->columns(5)
                            ->default(static::getStandardHours())
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make(__('core::core.settings'))
                    ->schema([
                        Forms\Components\Select::make('timezone')
                            ->label(__('core::core.timezone'))
                            ->options(static::getTimezoneOptions())
                            ->searchable()
                            ->default('Africa/Cairo')
                            ->columnSpan(1),

                        Forms\Components\Select::make('currency_code')
                            ->label(__('core::core.currency'))
                            ->options(static::getCurrencyOptions())
                            ->searchable()
                            ->default('EGP')
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('core::core.active'))
                            ->default(true)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('core::core.sort_order'))
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('core::core.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('core::core.code'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('city')
                    ->label(__('core::core.city'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('core::core.phone'))
                    ->icon('heroicon-o-phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('rooms_count')
                    ->label(__('core::core.rooms'))
                    ->counts('rooms')
                    ->sortable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('core::core.staff'))
                    ->counts('users')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_main')
                    ->label(__('core::core.main'))
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('core::core.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('core::core.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('core::core.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('core::core.active')),

                Tables\Filters\TernaryFilter::make('is_main')
                    ->label(__('core::core.main_branch')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Branch $record) {
                        // Prevent deletion of main branch
                        if ($record->is_main) {
                            throw new \Exception(__('core::core.cannot_delete_main_branch'));
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RoomsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\BranchLimitWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'view' => Pages\ViewBranch::route('/{record}'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }

    protected static function getTimezoneOptions(): array
    {
        $timezones = [];
        foreach (timezone_identifiers_list() as $timezone) {
            $timezones[$timezone] = $timezone;
        }
        return $timezones;
    }

    /**
     * Get all MENA region currencies plus major international currencies.
     */
    protected static function getCurrencyOptions(): array
    {
        return [
            // Gulf Countries
            'AED' => 'UAE Dirham (AED) - د.إ',
            'SAR' => 'Saudi Riyal (SAR) - ر.س',
            'KWD' => 'Kuwaiti Dinar (KWD) - د.ك',
            'BHD' => 'Bahraini Dinar (BHD) - د.ب',
            'OMR' => 'Omani Rial (OMR) - ر.ع',
            'QAR' => 'Qatari Riyal (QAR) - ر.ق',

            // Levant
            'EGP' => 'Egyptian Pound (EGP) - ج.م',
            'JOD' => 'Jordanian Dinar (JOD) - د.أ',
            'LBP' => 'Lebanese Pound (LBP) - ل.ل',
            'SYP' => 'Syrian Pound (SYP) - ل.س',
            'IQD' => 'Iraqi Dinar (IQD) - د.ع',
            'YER' => 'Yemeni Rial (YER) - ر.ي',
            'ILS' => 'Israeli Shekel (ILS) - ₪',
            'PSE' => 'Palestinian Pound (PSE)',

            // North Africa
            'MAD' => 'Moroccan Dirham (MAD) - د.م',
            'DZD' => 'Algerian Dinar (DZD) - د.ج',
            'TND' => 'Tunisian Dinar (TND) - د.ت',
            'LYD' => 'Libyan Dinar (LYD) - د.ل',
            'SDG' => 'Sudanese Pound (SDG) - ج.س',
            'MRU' => 'Mauritanian Ouguiya (MRU)',

            // International
            'USD' => 'US Dollar (USD) - $',
            'EUR' => 'Euro (EUR) - €',
            'GBP' => 'British Pound (GBP) - £',
            'TRY' => 'Turkish Lira (TRY) - ₺',
        ];
    }

    /**
     * Standard business hours (9 AM - 6 PM, Friday off).
     */
    protected static function getStandardHours(): array
    {
        return [
            ['day' => 'sunday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'monday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'tuesday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'wednesday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'thursday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'friday', 'open_time' => null, 'close_time' => null, 'is_closed' => true],
            ['day' => 'saturday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
        ];
    }

    /**
     * Extended hours (8 AM - 10 PM, all days).
     */
    protected static function getExtendedHours(): array
    {
        return [
            ['day' => 'sunday', 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
            ['day' => 'monday', 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
            ['day' => 'tuesday', 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
            ['day' => 'wednesday', 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
            ['day' => 'thursday', 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
            ['day' => 'friday', 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
            ['day' => 'saturday', 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
        ];
    }

    /**
     * Clinic hours (10 AM - 8 PM, Friday off).
     */
    protected static function getClinicHours(): array
    {
        return [
            ['day' => 'sunday', 'open_time' => '10:00', 'close_time' => '20:00', 'is_closed' => false],
            ['day' => 'monday', 'open_time' => '10:00', 'close_time' => '20:00', 'is_closed' => false],
            ['day' => 'tuesday', 'open_time' => '10:00', 'close_time' => '20:00', 'is_closed' => false],
            ['day' => 'wednesday', 'open_time' => '10:00', 'close_time' => '20:00', 'is_closed' => false],
            ['day' => 'thursday', 'open_time' => '10:00', 'close_time' => '20:00', 'is_closed' => false],
            ['day' => 'friday', 'open_time' => null, 'close_time' => null, 'is_closed' => true],
            ['day' => 'saturday', 'open_time' => '10:00', 'close_time' => '20:00', 'is_closed' => false],
        ];
    }

    /**
     * 24/7 hours.
     */
    protected static function get24Hours(): array
    {
        return [
            ['day' => 'sunday', 'open_time' => '00:00', 'close_time' => '23:59', 'is_closed' => false],
            ['day' => 'monday', 'open_time' => '00:00', 'close_time' => '23:59', 'is_closed' => false],
            ['day' => 'tuesday', 'open_time' => '00:00', 'close_time' => '23:59', 'is_closed' => false],
            ['day' => 'wednesday', 'open_time' => '00:00', 'close_time' => '23:59', 'is_closed' => false],
            ['day' => 'thursday', 'open_time' => '00:00', 'close_time' => '23:59', 'is_closed' => false],
            ['day' => 'friday', 'open_time' => '00:00', 'close_time' => '23:59', 'is_closed' => false],
            ['day' => 'saturday', 'open_time' => '00:00', 'close_time' => '23:59', 'is_closed' => false],
        ];
    }
}
