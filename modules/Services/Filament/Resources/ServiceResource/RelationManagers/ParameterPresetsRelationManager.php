<?php

namespace Modules\Services\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\ParameterPreset;

class ParameterPresetsRelationManager extends RelationManager
{
    protected static string $relationship = 'parameterPresets';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('services::services.labels.parameter_presets');
    }

    public function form(Form $form): Form
    {
        $service = $this->getOwnerRecord();
        $parameterDefinitions = $service->getParameterDefinitions();

        return $form
            ->schema([
                Forms\Components\Section::make(__('services::services.presets.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('services::services.fields.name') . ' (English)')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('services::services.fields.name') . ' (Arabic)')
                                    ->maxLength(100),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label(__('services::services.fields.description') . ' (English)')
                                    ->rows(2),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label(__('services::services.fields.description') . ' (Arabic)')
                                    ->rows(2),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('services::services.fields.is_active'))
                                    ->default(true),

                                Forms\Components\Toggle::make('is_default')
                                    ->label(__('services::services.presets.is_default'))
                                    ->helperText(__('services::services.presets.is_default_help')),
                            ]),
                    ]),

                Forms\Components\Section::make(__('services::services.presets.parameter_values'))
                    ->description(__('services::services.presets.parameter_values_description'))
                    ->schema(function () use ($parameterDefinitions) {
                        if (empty($parameterDefinitions)) {
                            return [
                                Forms\Components\Placeholder::make('no_parameters')
                                    ->content(__('services::services.messages.no_parameters_defined'))
                                    ->columnSpanFull(),
                            ];
                        }

                        $fields = [];
                        foreach ($parameterDefinitions as $param) {
                            $key = $param['key'] ?? '';
                            $label = $param['label'][app()->getLocale()] ?? $param['label']['en'] ?? $key;
                            $type = $param['type'] ?? 'text';
                            $unit = $param['unit'] ?? null;

                            $field = match ($type) {
                                'number', 'decimal' => Forms\Components\TextInput::make("values.{$key}")
                                    ->label($label)
                                    ->numeric()
                                    ->step($type === 'decimal' ? 0.1 : 1)
                                    ->minValue($param['min'] ?? null)
                                    ->maxValue($param['max'] ?? null)
                                    ->suffix($unit),

                                'select' => Forms\Components\Select::make("values.{$key}")
                                    ->label($label)
                                    ->options(function () use ($param) {
                                        $options = $param['options'] ?? [];
                                        $result = [];
                                        foreach ($options as $opt) {
                                            if (is_array($opt)) {
                                                $value = $opt['value'] ?? '';
                                                $optLabel = $opt['label'][app()->getLocale()] ?? $opt['label']['en'] ?? $value;
                                                $result[$value] = $optLabel;
                                            } else {
                                                $result[$opt] = $opt;
                                            }
                                        }
                                        return $result;
                                    }),

                                'boolean' => Forms\Components\Toggle::make("values.{$key}")
                                    ->label($label),

                                'textarea' => Forms\Components\Textarea::make("values.{$key}")
                                    ->label($label)
                                    ->rows(3),

                                default => Forms\Components\TextInput::make("values.{$key}")
                                    ->label($label)
                                    ->suffix($unit),
                            };

                            $fields[] = $field;
                        }

                        return $fields;
                    })
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('translated_name')
                    ->label(__('services::services.fields.name'))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('translated_description')
                    ->label(__('services::services.fields.description'))
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('services::services.presets.is_default'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('services::services.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('services::services.presets.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('services::services.fields.is_active')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->hasParameters()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('setDefault')
                    ->label(__('services::services.presets.set_as_default'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (ParameterPreset $record) => !$record->is_default && $record->is_active)
                    ->action(function (ParameterPreset $record) {
                        // Remove default from other presets
                        $this->getOwnerRecord()->parameterPresets()
                            ->where('is_default', true)
                            ->update(['is_default' => false]);

                        // Set this as default
                        $record->update(['is_default' => true]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('services::services.presets.no_presets'))
            ->emptyStateDescription(fn () => $this->getOwnerRecord()->hasParameters()
                ? __('services::services.presets.no_presets_description')
                : __('services::services.presets.configure_parameters_first')
            );
    }
}
