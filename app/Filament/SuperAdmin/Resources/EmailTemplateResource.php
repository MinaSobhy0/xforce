<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Resources\Concerns\Translatable;

class EmailTemplateResource extends Resource
{
    use Translatable;

    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Email Templates';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Template Information')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->helperText('Unique identifier for this template'),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('trigger')
                        ->options(EmailTemplate::TRIGGERS)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Email Content')
                ->description('Use the locale switcher in the header to edit content in different languages')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label('Subject')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\RichEditor::make('body')
                        ->label('Body')
                        ->required()
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'link',
                            'bulletList',
                            'orderedList',
                            'h2',
                            'h3',
                        ]),
                ]),

            Forms\Components\Section::make('Available Variables')
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('variables_help')
                        ->label('')
                        ->content(function () {
                            $vars = collect(EmailTemplate::AVAILABLE_VARIABLES)
                                ->map(fn($desc, $var) => "<code>{" . $var . "}</code> - " . $desc)
                                ->join('<br>');
                            return new \Illuminate\Support\HtmlString($vars);
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('trigger')
                    ->formatStateUsing(fn($state) => EmailTemplate::TRIGGERS[$state] ?? $state)
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('trigger')
                    ->options(EmailTemplate::TRIGGERS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn($record) => 'Preview: ' . $record->name)
                    ->modalContent(fn($record) => view('filament.super-admin.modals.email-template-preview', ['record' => $record])),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check')
                    ->action(fn($records) => $records->each->update(['is_active' => true])),

                Tables\Actions\BulkAction::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-mark')
                    ->action(fn($records) => $records->each->update(['is_active' => false])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
