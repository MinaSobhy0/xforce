<?php

namespace Modules\Accounting\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Accounting\Filament\Resources\JournalEntryResource\Pages;
use Modules\Accounting\Filament\Resources\JournalEntryResource\RelationManagers;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\FiscalPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class JournalEntryResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = JournalEntry::class;

    protected static ?string $moduleCode = 'accounting';

    protected static ?string $permissionKey = 'invoices';

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 53;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Entry Details')
                    ->schema([
                        Forms\Components\Select::make('journal_id')
                            ->label('Journal')
                            ->options(fn () => Journal::active()->get()->pluck('display_name', 'id'))
                            ->required()
                            ->searchable()
                            ->default(fn () => Journal::getMiscJournal()?->id),

                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reference')
                            ->maxLength(255),

                        Forms\Components\Select::make('fiscal_period_id')
                            ->label('Fiscal Period')
                            ->relationship('fiscalPeriod', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => FiscalPeriod::getCurrent()?->id),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Journal Lines')
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('account_id')
                                    ->label('Account')
                                    ->options(ChartOfAccount::active()->postable()->get()->pluck('display_name', 'id'))
                                    ->searchable()
                                    ->required(),

                                Forms\Components\TextInput::make('debit_minor')
                                    ->label('Debit')
                                    ->numeric()
                                    ->default(0)
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                                Forms\Components\TextInput::make('credit_minor')
                                    ->label('Credit')
                                    ->numeric()
                                    ->default(0)
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : 0)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                                Forms\Components\TextInput::make('description')
                                    ->maxLength(255),
                            ])
                            ->columns(4)
                            ->defaultItems(2)
                            ->addActionLabel('Add Line')
                            ->reorderable(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('journal.code')
                    ->label('Journal')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->journal?->type_color ?? 'gray'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Entry #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_debit_minor')
                    ->label('Debit')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_credit_minor')
                    ->label('Credit')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->alignEnd(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => JournalEntry::STATUS_DRAFT,
                        'success' => JournalEntry::STATUS_POSTED,
                        'danger' => JournalEntry::STATUS_CANCELLED,
                    ]),

                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Posted')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('journal_id')
                    ->label('Journal')
                    ->relationship('journal', 'code'),

                Tables\Filters\SelectFilter::make('status')
                    ->options(JournalEntry::STATUSES),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (JournalEntry $record) => $record->isDraft()),

                Tables\Actions\Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (JournalEntry $record) => $record->isDraft() && $record->isBalanced())
                    ->action(fn (JournalEntry $record) => $record->post()),

                Tables\Actions\Action::make('reverse')
                    ->label('Reverse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (JournalEntry $record) => $record->isPosted() && !$record->isReversed())
                    ->form([
                        Forms\Components\Textarea::make('description')
                            ->label('Reversal Description')
                            ->required(),
                    ])
                    ->action(fn (JournalEntry $record, array $data) => $record->reverse($data['description'])),
            ])
            ->bulkActions([])
            ->defaultSort('date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['journal', 'fiscalPeriod', 'createdBy']);
    }
}
