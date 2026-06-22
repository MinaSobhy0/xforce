<?php

namespace Modules\Core\Resources;

use XLinic\Framework\Core\Filament\BaseResource;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Modules\Core\Resources\TenantResource\Pages;
use Modules\Core\Resources\TenantResource\RelationManagers;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;

class TenantResource extends BaseResource
{
    protected static ?string $model = Tenant::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'System Management';
    protected static ?int $navigationSort = 10;
    protected static ?string $moduleCode = 'core';

    public static function getNavigationLabel(): string
    {
        return __('Tenants');
    }

    public static function getModelLabel(): string
    {
        return __('Tenant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tenants');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Tenant Information')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Tenant Name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $context, $state, Forms\Set $set) {
                                        if ($context === 'create') {
                                            $set('slug', \Illuminate\Support\Str::slug($state));
                                        }
                                    }),

                                Forms\Components\TextInput::make('slug')
                                    ->label(__('Slug'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->helperText(__('Used in subdomain and database schema name')),

                                Forms\Components\TextInput::make('domain')
                                    ->label(__('Custom Domain'))
                                    ->url()
                                    ->nullable()
                                    ->unique(ignoreRecord: true)
                                    ->helperText(__('Optional custom domain (e.g., myclinic.com)')),

                                Forms\Components\Select::make('status')
                                    ->label(__('Status'))
                                    ->options(TenantStatus::class)
                                    ->required()
                                    ->default(TenantStatus::PENDING),

                                Forms\Components\DateTimePicker::make('subscription_expires_at')
                                    ->label(__('Subscription Expires At'))
                                    ->nullable(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Contact Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\TextInput::make('contact_name')
                                    ->label(__('Contact Name'))
                                    ->nullable()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('contact_email')
                                    ->label(__('Contact Email'))
                                    ->email()
                                    ->nullable()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('contact_phone')
                                    ->label(__('Contact Phone'))
                                    ->tel()
                                    ->nullable()
                                    ->maxLength(20),

                                Forms\Components\Textarea::make('address')
                                    ->label(__('Address'))
                                    ->nullable()
                                    ->rows(3),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('city')
                                            ->label(__('City'))
                                            ->nullable()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('postal_code')
                                            ->label(__('Postal Code'))
                                            ->nullable()
                                            ->maxLength(20),
                                    ]),

                                Forms\Components\Select::make('country')
                                    ->label(__('Country'))
                                    ->options([
                                        'EG' => __('Egypt'),
                                        'SA' => __('Saudi Arabia'),
                                        'AE' => __('United Arab Emirates'),
                                        'KW' => __('Kuwait'),
                                        'QA' => __('Qatar'),
                                        'BH' => __('Bahrain'),
                                        'OM' => __('Oman'),
                                        'JO' => __('Jordan'),
                                        'LB' => __('Lebanon'),
                                        'SY' => __('Syria'),
                                    ])
                                    ->default('EG')
                                    ->searchable(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Limits & Features')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('max_users')
                                            ->label(__('Max Users'))
                                            ->numeric()
                                            ->default(10)
                                            ->minValue(1)
                                            ->maxValue(1000),

                                        Forms\Components\TextInput::make('max_patients')
                                            ->label(__('Max Patients'))
                                            ->numeric()
                                            ->default(1000)
                                            ->minValue(10)
                                            ->maxValue(100000),

                                        Forms\Components\TextInput::make('max_storage_mb')
                                            ->label(__('Max Storage (MB)'))
                                            ->numeric()
                                            ->default(1024)
                                            ->minValue(100)
                                            ->maxValue(10000),
                                    ]),

                                Forms\Components\CheckboxList::make('features')
                                    ->label(__('Available Features'))
                                    ->options([
                                        'users' => __('User Management'),
                                        'patients' => __('Patient Management'),
                                        'appointments' => __('Appointment Scheduling'),
                                        'treatments' => __('Treatment Catalog'),
                                        'inventory' => __('Inventory Management'),
                                        'billing' => __('Billing & Invoicing'),
                                        'reports' => __('Reports & Analytics'),
                                        'marketing' => __('Marketing Tools'),
                                        'portal' => __('Patient Portal'),
                                        'api' => __('API Access'),
                                    ])
                                    ->default(['users', 'patients', 'appointments', 'treatments'])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Branding')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\FileUpload::make('logo_url')
                                    ->label(__('Logo'))
                                    ->image()
                                    ->directory('tenants/logos')
                                    ->visibility('public')
                                    ->nullable(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label(__('Primary Color'))
                                            ->nullable(),

                                        Forms\Components\ColorPicker::make('secondary_color')
                                            ->label(__('Secondary Color'))
                                            ->nullable(),
                                    ]),

                                Forms\Components\Textarea::make('custom_css')
                                    ->label(__('Custom CSS'))
                                    ->nullable()
                                    ->rows(5)
                                    ->helperText(__('Custom CSS for tenant branding')),
                            ]),

                        Forms\Components\Tabs\Tab::make('Localization')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('timezone')
                                            ->label(__('Timezone'))
                                            ->options([
                                                'Africa/Cairo' => 'Cairo (GMT+2)',
                                                'Asia/Riyadh' => 'Riyadh (GMT+3)',
                                                'Asia/Dubai' => 'Dubai (GMT+4)',
                                                'Asia/Kuwait' => 'Kuwait (GMT+3)',
                                                'Asia/Qatar' => 'Qatar (GMT+3)',
                                            ])
                                            ->default('Africa/Cairo')
                                            ->searchable(),

                                        Forms\Components\Select::make('locale')
                                            ->label(__('Language'))
                                            ->options([
                                                'ar' => __('Arabic'),
                                                'en' => __('English'),
                                            ])
                                            ->default('en'),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('currency')
                                            ->label(__('Currency'))
                                            ->options([
                                                'EGP' => 'Egyptian Pound (EGP)',
                                                'SAR' => 'Saudi Riyal (SAR)',
                                                'AED' => 'UAE Dirham (AED)',
                                                'KWD' => 'Kuwaiti Dinar (KWD)',
                                                'QAR' => 'Qatari Riyal (QAR)',
                                                'USD' => 'US Dollar (USD)',
                                            ])
                                            ->default('EGP')
                                            ->searchable(),

                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label(__('Tax Rate (%)'))
                                            ->numeric()
                                            ->default(14.00)
                                            ->minValue(0)
                                            ->maxValue(30)
                                            ->step(0.01)
                                            ->suffix('%'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_url')
                    ->label(__('Logo'))
                    ->circular()
                    ->defaultImageUrl(url('/images/default-tenant.png')),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('Status')),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label(__('Contact'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('Users'))
                    ->counts('users')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subscription_expires_at')
                    ->label(__('Expires'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->color(fn($record) => $record?->subscription_expires_at?->isPast() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(TenantStatus::class),

                Tables\Filters\Filter::make('expired')
                    ->label(__('Expired'))
                    ->query(fn (Builder $query): Builder => $query->where('subscription_expires_at', '<', now())),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label(__('Expiring Soon'))
                    ->query(fn (Builder $query): Builder => $query->whereBetween('subscription_expires_at', [now(), now()->addDays(30)])),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('impersonate')
                    ->label(__('Login'))
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('info')
                    ->url(fn (Tenant $record): string => route('tenant.switch', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label(__('Activate'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => TenantStatus::ACTIVE])),
                    Tables\Actions\BulkAction::make('suspend')
                        ->label(__('Suspend'))
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['status' => TenantStatus::SUSPENDED])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('Tenant Overview'))
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')
                                        ->label(__('Name'))
                                        ->weight(FontWeight::Bold),
                                    Infolists\Components\TextEntry::make('slug')
                                        ->label(__('Slug'))
                                        ->badge(),
                                    Infolists\Components\TextEntry::make('status')
                                        ->label(__('Status'))
                                        ->badge(),
                                    Infolists\Components\TextEntry::make('domain')
                                        ->label(__('Domain'))
                                        ->url(fn ($record) => $record->domain ? "https://{$record->domain}" : null)
                                        ->openUrlInNewTab(),
                                ]),
                            Infolists\Components\ImageEntry::make('logo_url')
                                ->hiddenLabel()
                                ->grow(false),
                        ])->from('lg'),
                    ]),

                Infolists\Components\Section::make(__('Usage Statistics'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('users_count')
                                    ->label(__('Users'))
                                    // Active-only count so this matches the
                                    // seat-cap gate. See User::countExistingRecords
                                    // and UserLimitObserver — inactive /
                                    // suspended / pending users don't burn a seat.
                                    ->state(fn ($record) => $record->users()->where('status', 'active')->count() . ' / ' . $record->max_users)
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('max_patients')
                                    ->label(__('Patients Limit'))
                                    ->numeric(),
                                Infolists\Components\TextEntry::make('max_storage_mb')
                                    ->label(__('Storage Limit'))
                                    ->state(fn ($record) => number_format($record->max_storage_mb) . ' MB'),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('Contact Information'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('contact_name')
                                    ->label(__('Contact Name')),
                                Infolists\Components\TextEntry::make('contact_email')
                                    ->label(__('Email'))
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('contact_phone')
                                    ->label(__('Phone'))
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('city')
                                    ->label(__('City')),
                            ]),
                        Infolists\Components\TextEntry::make('address')
                            ->label(__('Address'))
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
            RelationManagers\SubscriptionRelationManager::class,
            RelationManagers\UsageRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?string $slug = 'tenants';
}