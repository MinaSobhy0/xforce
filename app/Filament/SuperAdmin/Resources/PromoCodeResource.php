<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PromoCodeResource\Pages;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Promo Codes';

    protected static ?string $navigationGroup = 'Financials';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Code Details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                        ->dehydrateStateUsing(fn($state) => strtoupper($state)),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Select::make('discount_type')
                        ->options(PromoCode::DISCOUNT_TYPES)
                        ->default('percentage')
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('discount_value')
                        ->numeric()
                        ->required()
                        ->suffix(fn(Forms\Get $get) => $get('discount_type') === 'percentage' ? '%' : 'EGP'),

                    Forms\Components\TextInput::make('discount_duration_months')
                        ->label('Duration (months)')
                        ->numeric()
                        ->placeholder('Forever if empty')
                        ->helperText('How many months the discount applies'),

                    Forms\Components\TextInput::make('max_uses')
                        ->label('Max Uses')
                        ->numeric()
                        ->placeholder('Unlimited if empty'),
                ]),

            Forms\Components\Section::make('Validity')
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('valid_from')
                        ->label('Valid From'),

                    Forms\Components\DateTimePicker::make('valid_until')
                        ->label('Valid Until'),

                    Forms\Components\Select::make('min_plan_tier')
                        ->label('Minimum Plan Required')
                        ->options([
                            'starter' => 'Starter',
                            'professional' => 'Professional',
                            'enterprise' => 'Enterprise',
                        ])
                        ->placeholder('Any plan'),

                    Forms\Components\CheckboxList::make('applicable_plan_ids')
                        ->label('Applicable Plans')
                        ->options(fn() => SubscriptionPlan::active()->pluck('code', 'id'))
                        ->columns(3)
                        ->helperText('Leave empty for all plans'),
                ]),

            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Internal Notes')
                        ->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),

                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->discount_type === 'percentage') {
                            return $state . '%';
                        }
                        return 'EGP ' . number_format($state);
                    })
                    ->color('success'),

                Tables\Columns\TextColumn::make('discount_duration_months')
                    ->label('Duration')
                    ->formatStateUsing(fn($state) => $state ? "{$state} months" : 'Forever')
                    ->color('info'),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Expires')
                    ->date()
                    ->placeholder('No expiry')
                    ->sortable(),

                Tables\Columns\TextColumn::make('used_count')
                    ->label('Used')
                    ->formatStateUsing(function ($state, $record) {
                        $max = $record->max_uses ?? '∞';
                        return "{$state}/{$max}";
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'gray',
                        'exhausted' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\Filter::make('valid')
                    ->label('Currently Valid')
                    ->query(fn($query) => $query->valid()),
            ])
            ->actions([
                Tables\Actions\Action::make('copy')
                    ->label('Copy Code')
                    ->icon('heroicon-o-clipboard')
                    ->action(function ($record) {
                        // Client-side copy handled by copyable()
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->is_active)
                    ->action(fn($record) => $record->update(['is_active' => false])),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
