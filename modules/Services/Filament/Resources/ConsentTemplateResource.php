<?php

namespace Modules\Services\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\ConsentTemplate;
use Modules\Services\Filament\Resources\ConsentTemplateResource\Pages;

class ConsentTemplateResource extends Resource
{
    protected static ?string $model = ConsentTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Services';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('services::services.navigation.consent_templates');
    }

    public static function getModelLabel(): string
    {
        return __('services::services.labels.consent_template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('services::services.labels.consent_templates');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('services::services.consent.name') . ' (English)')
                                    ->required()
                                    ->maxLength(150),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('services::services.consent.name') . ' (Arabic)')
                                    ->required()
                                    ->maxLength(150),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label('Description (English)')
                                    ->rows(2),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label('Description (Arabic)')
                                    ->rows(2),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('version')
                                    ->label(__('services::services.consent.version'))
                                    ->default('1.0')
                                    ->required()
                                    ->maxLength(10),

                                Forms\Components\TextInput::make('valid_days')
                                    ->label(__('services::services.consent.valid_days'))
                                    ->numeric()
                                    ->nullable()
                                    ->helperText('Leave empty for no expiration'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('services::services.consent.is_active'))
                                    ->default(true),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('requires_witness')
                                    ->label('Requires Witness Signature')
                                    ->default(false),

                                Forms\Components\Toggle::make('requires_patient_signature')
                                    ->label('Requires Patient Signature')
                                    ->default(true),
                            ]),
                    ]),

                Forms\Components\Section::make('Consent Content')
                    ->schema([
                        Forms\Components\RichEditor::make('content.en')
                            ->label(__('services::services.consent.content') . ' (English)')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('content.ar')
                            ->label(__('services::services.consent.content') . ' (Arabic)')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translated_name')
                    ->label(__('services::services.consent.name'))
                    ->searchable(['name'])
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('version')
                    ->label(__('services::services.consent.version'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_days')
                    ->label(__('services::services.consent.valid_days'))
                    ->suffix(' days')
                    ->placeholder('No expiration')
                    ->sortable(),

                Tables\Columns\TextColumn::make('services_count')
                    ->label('Services')
                    ->counts('services')
                    ->sortable(),

                Tables\Columns\TextColumn::make('signed_forms_count')
                    ->label('Signed')
                    ->counts('signedForms')
                    ->sortable(),

                Tables\Columns\IconColumn::make('requires_witness')
                    ->label('Witness')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('services::services.consent.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('services::services.consent.is_active')),

                Tables\Filters\TernaryFilter::make('requires_witness')
                    ->label('Requires Witness'),
            ])
            ->actions([
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(fn (ConsentTemplate $record) => $record->duplicate())
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('increment_version')
                    ->label('New Version')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->action(fn (ConsentTemplate $record) => $record->incrementVersion())
                    ->requiresConfirmation(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsentTemplates::route('/'),
            'create' => Pages\CreateConsentTemplate::route('/create'),
            'view' => Pages\ViewConsentTemplate::route('/{record}'),
            'edit' => Pages\EditConsentTemplate::route('/{record}/edit'),
        ];
    }
}
