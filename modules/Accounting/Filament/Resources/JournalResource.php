<?php

namespace Modules\Accounting\Filament\Resources;

use Modules\Accounting\Filament\Resources\JournalResource\Pages;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class JournalResource extends Resource
{
    protected static ?string $model = Journal::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 24;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.journals');
    }

    public static function getModelLabel(): string
    {
        return __('accounting::accounting.journal');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('name.en')
                            ->label('Name (English)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('name.ar')
                            ->label('Name (Arabic)')
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->options(Journal::TYPES)
                            ->required(),

                        Forms\Components\TextInput::make('sequence_prefix')
                            ->label('Sequence Prefix')
                            ->required()
                            ->maxLength(10)
                            ->helperText('e.g., INV, BILL, BNK, CSH, MISC'),

                        Forms\Components\Select::make('default_debit_account_id')
                            ->label('Default Debit Account')
                            ->options(fn () => ChartOfAccount::active()->postable()->get()->pluck('display_name', 'id'))
                            ->searchable(),

                        Forms\Components\Select::make('default_credit_account_id')
                            ->label('Default Credit Account')
                            ->options(fn () => ChartOfAccount::active()->postable()->get()->pluck('display_name', 'id'))
                            ->searchable(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->formatStateUsing(fn ($state) => Journal::TYPES[$state] ?? $state)
                    ->colors([
                        'success' => Journal::TYPE_SALES,
                        'warning' => Journal::TYPE_PURCHASE,
                        'info' => Journal::TYPE_CASH,
                        'primary' => Journal::TYPE_BANK,
                        'gray' => Journal::TYPE_GENERAL,
                    ]),

                Tables\Columns\TextColumn::make('sequence_prefix')
                    ->label('Prefix'),

                Tables\Columns\TextColumn::make('next_sequence')
                    ->label('Next #'),

                Tables\Columns\TextColumn::make('entries_count')
                    ->label('Entries')
                    ->counts('entries'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(Journal::TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournals::route('/'),
            'create' => Pages\CreateJournal::route('/create'),
            'edit' => Pages\EditJournal::route('/{record}/edit'),
        ];
    }
}
