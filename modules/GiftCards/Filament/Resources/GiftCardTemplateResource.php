<?php

namespace Modules\GiftCards\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\GiftCards\Filament\Resources\GiftCardTemplateResource\Pages;
use Modules\GiftCards\Models\GiftCardTemplate;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Services\GiftCardService;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class GiftCardTemplateResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = GiftCardTemplate::class;

    protected static ?string $moduleCode = 'giftcards';

    protected static ?string $permissionKey = 'gift_card_templates';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Marketing';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.loyalty_gifts');
    }

    protected static ?int $navigationSort = 51;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('giftcards::giftcards.templates');
    }

    public static function getModelLabel(): string
    {
        return __('giftcards::giftcards.template.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('giftcards::giftcards.templates');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('giftcards::giftcards.template.sections.basic'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('giftcards::giftcards.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label(__('giftcards::giftcards.fields.description'))
                            ->rows(2)
                            ->maxLength(1000),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('giftcards::giftcards.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(1),

                Forms\Components\Section::make(__('giftcards::giftcards.template.sections.value_config'))
                    ->schema([
                        Forms\Components\TextInput::make('amount_minor')
                            ->label(__('giftcards::giftcards.fields.amount'))
                            ->numeric()
                            ->required()
                            ->default(10000)
                            ->suffix(current_currency())
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : 100)
                            ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)),

                        Forms\Components\TextInput::make('validity_days')
                            ->label(__('giftcards::giftcards.template.validity_days'))
                            ->numeric()
                            ->required()
                            ->default(365)
                            ->suffix(__('giftcards::giftcards.days')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('giftcards::giftcards.template.sections.discount'))
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->label(__('giftcards::giftcards.template.discount_type'))
                            ->options([
                                GiftCardTemplate::DISCOUNT_PERCENTAGE => __('giftcards::giftcards.template.discount_percentage'),
                                GiftCardTemplate::DISCOUNT_FIXED => __('giftcards::giftcards.template.discount_fixed'),
                            ])
                            ->placeholder(__('giftcards::giftcards.template.no_discount')),

                        Forms\Components\TextInput::make('discount_value')
                            ->label(__('giftcards::giftcards.template.discount_value'))
                            ->numeric()
                            ->default(0)
                            ->hint(__('giftcards::giftcards.template.discount_hint')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('giftcards::giftcards.template.sections.gl_accounts'))
                    ->schema([
                        Forms\Components\Select::make('liability_account_id')
                            ->label(__('giftcards::giftcards.template.liability_account'))
                            ->options(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_CURRENT_LIABILITY)
                                ->where('is_active', true)
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->hint(__('giftcards::giftcards.template.liability_hint')),

                        Forms\Components\Select::make('expense_account_id')
                            ->label(__('giftcards::giftcards.template.expense_account'))
                            ->options(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_EXPENSE)
                                ->where('is_active', true)
                                ->get()
                                ->pluck('display_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->hint(__('giftcards::giftcards.template.expense_hint')),

                        Forms\Components\Select::make('sales_journal_id')
                            ->label(__('giftcards::giftcards.template.sales_journal'))
                            ->options(fn () => Journal::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('giftcards::giftcards.template.sections.behavior'))
                    ->schema([
                        Forms\Components\Toggle::make('allow_partial_redemption')
                            ->label(__('giftcards::giftcards.template.allow_partial'))
                            ->default(true)
                            ->helperText(__('giftcards::giftcards.template.allow_partial_hint')),

                        Forms\Components\Toggle::make('requires_activation')
                            ->label(__('giftcards::giftcards.template.requires_activation'))
                            ->default(true)
                            ->helperText(__('giftcards::giftcards.template.requires_activation_hint')),
                    ])
                    ->columns(2),
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

                Tables\Columns\TextColumn::make('name')
                    ->label(__('giftcards::giftcards.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('giftcards::giftcards.fields.amount'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('gift_cards_count')
                    ->counts('giftCards')
                    ->label(__('giftcards::giftcards.template.cards_count')),

                Tables\Columns\TextColumn::make('validity_days')
                    ->label(__('giftcards::giftcards.template.validity_days'))
                    ->suffix(' ' . __('giftcards::giftcards.days')),

                Tables\Columns\TextColumn::make('discount_type')
                    ->label(__('giftcards::giftcards.template.discount'))
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state) {
                            return '-';
                        }
                        $value = $record->discount_value;
                        return $state === 'percentage' ? "{$value}%" : format_money($value * 100);
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('giftcards::giftcards.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('giftcards::giftcards.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('giftcards::giftcards.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('generate_batch')
                    ->label(__('giftcards::giftcards.actions.generate_batch'))
                    ->icon('heroicon-o-squares-plus')
                    ->color('success')
                    ->visible(fn (GiftCardTemplate $record) => $record->is_active)
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label(__('giftcards::giftcards.template.quantity'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(10),

                        Forms\Components\Toggle::make('generate_pin')
                            ->label(__('giftcards::giftcards.template.generate_pin'))
                            ->default(false),
                    ])
                    ->action(function (GiftCardTemplate $record, array $data) {
                        try {
                            $cards = app(GiftCardService::class)->generateBatch(
                                $record,
                                $data['quantity'],
                                ['generate_pin' => $data['generate_pin'] ?? false]
                            );

                            Notification::make()
                                ->title(__('giftcards::giftcards.messages.batch_generated', [
                                    'count' => $cards->count(),
                                ]))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title(__('giftcards::giftcards.messages.batch_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view_statistics')
                    ->label(__('giftcards::giftcards.actions.view_statistics'))
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->modalHeading(fn (GiftCardTemplate $record) => $record->name . ' - ' . __('giftcards::giftcards.statistics'))
                    ->modalContent(fn (GiftCardTemplate $record) => view('giftcards::filament.template-statistics', [
                        'stats' => $record->getStatistics(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('giftcards::giftcards.close')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGiftCardTemplates::route('/'),
            'create' => Pages\CreateGiftCardTemplate::route('/create'),
            'view' => Pages\ViewGiftCardTemplate::route('/{record}'),
            'edit' => Pages\EditGiftCardTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['liabilityAccount', 'expenseAccount', 'createdBy']);
    }
}
