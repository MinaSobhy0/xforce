<?php

namespace Modules\Core\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Core\Filament\Resources\DepartmentResource\Pages;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Staff\Models\StaffProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepartmentResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Department::class;

    protected static ?string $moduleCode = 'core';

    protected static ?string $permissionKey = 'departments';

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('core::core.departments');
    }

    public static function getModelLabel(): string
    {
        return __('core::core.department');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::core.departments');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('core::core.department_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('core::core.name'))
                            ->required()
                            ->maxLength(150)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('code')
                            ->label(__('core::core.code'))
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('core::core.active'))
                            ->default(true)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->label(__('core::core.description'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('core::core.organization'))
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label(__('core::core.parent_department'))
                            ->relationship(
                                'parent',
                                'name',
                                fn (Builder $query, ?Department $record) => $query
                                    ->where('is_active', true)
                                    ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder(__('core::core.no_parent'))
                            ->columnSpan(1),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('core::core.branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        Forms\Components\Select::make('manager_id')
                            ->label(__('core::core.manager'))
                            ->relationship(
                                'manager',
                                'id',
                                fn (Builder $query) => $query->with('user')->where('is_active', true)
                            )
                            ->getOptionLabelFromRecordUsing(fn (StaffProfile $record) => $record->user?->full_name ?? $record->user?->name ?? "Staff #{$record->id}")
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('core::core.sort_order'))
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('core::core.integration'))
                    ->schema([
                        Forms\Components\TextInput::make('odoo_id')
                            ->label(__('core::core.odoo_id'))
                            ->numeric(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('core::core.name'))
                    ->description(fn (Department $record) => $record->full_path !== $record->name ? $record->full_path : null)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('core::core.code'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label(__('core::core.parent_department'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('manager.user.name')
                    ->label(__('core::core.manager'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('core::core.branch'))
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('staff_count')
                    ->label(__('core::core.staff_count'))
                    ->counts('staff')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('core::core.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('core::core.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('core::core.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('odoo_id')
                    ->label(__('core::core.odoo_id'))
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('core::core.active')),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('core::core.branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label(__('core::core.parent_department'))
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Department $record) {
                        if ($record->hasChildren()) {
                            throw new \Exception(__('core::core.cannot_delete_department_with_children'));
                        }
                        if ($record->hasStaff()) {
                            throw new \Exception(__('core::core.cannot_delete_department_with_staff'));
                        }
                    }),
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
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'view' => Pages\ViewDepartment::route('/{record}'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['parent', 'branch', 'manager.user']);
    }
}
