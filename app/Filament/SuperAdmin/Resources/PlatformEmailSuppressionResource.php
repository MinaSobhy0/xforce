<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PlatformEmailSuppressionResource\Pages;
use App\Models\PlatformEmailSuppression;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformEmailSuppressionResource extends Resource
{
    protected static ?string $model = PlatformEmailSuppression::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Suppression';

    protected static ?string $pluralModelLabel = 'Email Suppressions';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->helperText('This address will be silently skipped by every future platform-marketing send.')
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn ($state) => mb_strtolower(trim((string) $state))),

            Forms\Components\Select::make('reason')
                ->options(PlatformEmailSuppression::REASONS)
                ->default(PlatformEmailSuppression::REASON_MANUAL)
                ->required(),

            Forms\Components\Textarea::make('note')
                ->rows(2)
                ->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('reason')
                    ->formatStateUsing(fn ($state) => PlatformEmailSuppression::REASONS[$state] ?? $state)
                    ->colors([
                        'gray' => PlatformEmailSuppression::REASON_MANUAL,
                        'warning' => PlatformEmailSuppression::REASON_UNSUBSCRIBED,
                        'danger' => PlatformEmailSuppression::REASON_BOUNCED,
                        'danger' => PlatformEmailSuppression::REASON_COMPLAINED,
                    ]),
                Tables\Columns\TextColumn::make('note')->wrap()->limit(60),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reason')->options(PlatformEmailSuppression::REASONS),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Rehabilitate')
                    ->modalHeading('Rehabilitate address?')
                    ->modalDescription('This address will start receiving platform marketing again on the next campaign.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Rehabilitate selected'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformEmailSuppressions::route('/'),
            'create' => Pages\CreatePlatformEmailSuppression::route('/create'),
        ];
    }
}
