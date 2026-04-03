<?php

namespace Modules\Projects\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Projects\Filament\Resources\ProjectTagResource\Pages;
use Modules\Projects\Models\ProjectTag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectTagResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = ProjectTag::class;

    protected static ?string $moduleCode = 'projects';

    protected static ?string $permissionKey = 'tags';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('projects::projects.tags');
    }

    public static function getModelLabel(): string
    {
        return __('projects::projects.tag');
    }

    public static function getPluralModelLabel(): string
    {
        return __('projects::projects.tags');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name.en')
                            ->label(__('projects::projects.fields.name_en'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('name.ar')
                            ->label(__('projects::projects.fields.name_ar'))
                            ->maxLength(255),

                        Forms\Components\ColorPicker::make('color')
                            ->label(__('projects::projects.fields.color'))
                            ->required()
                            ->default('#6B7280'),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('projects::projects.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label(__('projects::projects.fields.color')),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('projects::projects.fields.name'))
                    ->formatStateUsing(fn (ProjectTag $record) => $record->display_name)
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('tasks_count')
                    ->label(__('projects::projects.fields.tasks'))
                    ->counts('tasks')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('projects::projects.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('projects::projects.fields.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('projects::projects.filters.active')),
            ])
            ->actions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectTags::route('/'),
            'create' => Pages\CreateProjectTag::route('/create'),
            'edit' => Pages\EditProjectTag::route('/{record}/edit'),
        ];
    }
}
