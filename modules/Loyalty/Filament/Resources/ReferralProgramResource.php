<?php

namespace Modules\Loyalty\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Loyalty\Filament\Resources\ReferralProgramResource\Pages;
use Modules\Loyalty\Filament\Resources\ReferralProgramResource\RelationManagers;
use Modules\Loyalty\Models\ReferralProgram;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Concerns\Translatable;

class ReferralProgramResource extends Resource
{
    use Translatable;
    use ChecksResourcePermissions;

    protected static ?string $model = ReferralProgram::class;

    protected static ?string $moduleCode = 'loyalty';

    protected static ?string $permissionKey = 'memberships';

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 22;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('loyalty::loyalty.referral_programs');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty::loyalty.referral_program');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty::loyalty.referral_programs');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('loyalty::loyalty.sections.basic_info'))
                    ->schema([
                        Forms\Components\TextInput::make('name.en')
                            ->label(__('loyalty::loyalty.fields.name') . ' (EN)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('name.ar')
                            ->label(__('loyalty::loyalty.fields.name') . ' (AR)')
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description.en')
                            ->label(__('loyalty::loyalty.fields.description') . ' (EN)')
                            ->rows(2)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description.ar')
                            ->label(__('loyalty::loyalty.fields.description') . ' (AR)')
                            ->rows(2)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('loyalty::loyalty.fields.is_active'))
                            ->default(true)
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('loyalty::loyalty.sections.rewards'))
                    ->schema([
                        Forms\Components\TextInput::make('referrer_points')
                            ->label(__('loyalty::loyalty.fields.referrer_points'))
                            ->numeric()
                            ->minValue(0)
                            ->default(100)
                            ->helperText('Points awarded to the referrer'),

                        Forms\Components\TextInput::make('referred_points')
                            ->label(__('loyalty::loyalty.fields.referred_points'))
                            ->numeric()
                            ->minValue(0)
                            ->default(50)
                            ->helperText('Points awarded to the referred person'),

                        Forms\Components\TextInput::make('referrer_discount_percentage')
                            ->label(__('loyalty::loyalty.fields.referrer_discount'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Optional discount for the referrer'),

                        Forms\Components\TextInput::make('referred_discount_percentage')
                            ->label(__('loyalty::loyalty.fields.referred_discount'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Optional discount for the referred person'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('loyalty::loyalty.sections.requirements'))
                    ->schema([
                        Forms\Components\TextInput::make('min_purchase_minor')
                            ->label(__('loyalty::loyalty.fields.min_spend'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn () => current_currency())
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null)
                            ->helperText('Minimum purchase amount to qualify'),

                        Forms\Components\TextInput::make('max_referrals_per_patient')
                            ->label('Max Referrals per Patient')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Leave empty for unlimited'),

                        Forms\Components\Toggle::make('require_first_purchase')
                            ->label(__('loyalty::loyalty.fields.require_first_purchase'))
                            ->default(true)
                            ->helperText('Reward only after referred makes a purchase')
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('loyalty::loyalty.sections.validity'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label(__('loyalty::loyalty.fields.starts_at'))
                            ->helperText('Leave empty to start immediately'),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label(__('loyalty::loyalty.fields.ends_at'))
                            ->helperText('Leave empty for no end date'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('loyalty::loyalty.fields.name'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state['en'] ?? $state['ar'] ?? '') : $state)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('referrer_points')
                    ->label(__('loyalty::loyalty.fields.referrer_points'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('referred_points')
                    ->label(__('loyalty::loyalty.fields.referred_points'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('referrals_count')
                    ->label(__('loyalty::loyalty.referrals'))
                    ->counts('referrals')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('loyalty::loyalty.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('loyalty::loyalty.fields.starts_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('loyalty::loyalty.fields.ends_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('core::core.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('loyalty::loyalty.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            RelationManagers\ReferralsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferralPrograms::route('/'),
            'create' => Pages\CreateReferralProgram::route('/create'),
            'view' => Pages\ViewReferralProgram::route('/{record}'),
            'edit' => Pages\EditReferralProgram::route('/{record}/edit'),
        ];
    }
}
