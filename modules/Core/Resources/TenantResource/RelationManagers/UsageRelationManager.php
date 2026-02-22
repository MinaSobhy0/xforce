<?php

namespace Modules\Core\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UsageRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'usageLogs';

    protected static ?string $recordTitleAttribute = 'metric_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('metric_name')
                            ->label(__('Metric'))
                            ->options([
                                'users' => __('User Count'),
                                'patients' => __('Patient Count'),
                                'appointments' => __('Appointment Count'),
                                'storage_mb' => __('Storage Usage (MB)'),
                                'api_calls' => __('API Calls'),
                                'emails_sent' => __('Emails Sent'),
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('value')
                            ->label(__('Value'))
                            ->numeric()
                            ->required(),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('recorded_at')
                            ->label(__('Recorded At'))
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('period')
                            ->label(__('Period'))
                            ->default('daily')
                            ->maxLength(20),
                    ]),

                Forms\Components\Textarea::make('metadata')
                    ->label(__('Additional Data'))
                    ->nullable()
                    ->helperText(__('JSON formatted additional information')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('metric_name')
            ->columns([
                Tables\Columns\TextColumn::make('metric_name')
                    ->label(__('Metric'))
                    ->badge()
                    ->colors([
                        'primary' => 'users',
                        'success' => 'patients',
                        'warning' => 'appointments',
                        'info' => 'storage_mb',
                        'gray' => 'api_calls',
                    ]),

                Tables\Columns\TextColumn::make('value')
                    ->label(__('Value'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period')
                    ->label(__('Period'))
                    ->badge(),

                Tables\Columns\TextColumn::make('recorded_at')
                    ->label(__('Recorded'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('percentage_of_limit')
                    ->label(__('% of Limit'))
                    ->state(function ($record) {
                        $tenant = $this->getOwnerRecord();

                        $limits = [
                            'users' => $tenant->max_users,
                            'patients' => $tenant->max_patients,
                            'storage_mb' => $tenant->max_storage_mb,
                        ];

                        if (isset($limits[$record->metric_name]) && $limits[$record->metric_name] > 0) {
                            return round(($record->value / $limits[$record->metric_name]) * 100, 1) . '%';
                        }

                        return '-';
                    })
                    ->color(function ($record) {
                        $tenant = $this->getOwnerRecord();

                        $limits = [
                            'users' => $tenant->max_users,
                            'patients' => $tenant->max_patients,
                            'storage_mb' => $tenant->max_storage_mb,
                        ];

                        if (isset($limits[$record->metric_name]) && $limits[$record->metric_name] > 0) {
                            $percentage = ($record->value / $limits[$record->metric_name]) * 100;

                            if ($percentage >= 90) return 'danger';
                            if ($percentage >= 75) return 'warning';
                            return 'success';
                        }

                        return 'gray';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metric_name')
                    ->label(__('Metric'))
                    ->options([
                        'users' => __('User Count'),
                        'patients' => __('Patient Count'),
                        'appointments' => __('Appointment Count'),
                        'storage_mb' => __('Storage Usage'),
                        'api_calls' => __('API Calls'),
                        'emails_sent' => __('Emails Sent'),
                    ]),

                Tables\Filters\SelectFilter::make('period')
                    ->label(__('Period'))
                    ->options([
                        'daily' => __('Daily'),
                        'weekly' => __('Weekly'),
                        'monthly' => __('Monthly'),
                    ]),

                Tables\Filters\Filter::make('over_limit')
                    ->label(__('Over Limit'))
                    ->query(function (Builder $query): Builder {
                        $tenant = $this->getOwnerRecord();

                        return $query->where(function ($q) use ($tenant) {
                            $q->where(function ($subQ) use ($tenant) {
                                $subQ->where('metric_name', 'users')
                                     ->where('value', '>', $tenant->max_users);
                            })
                            ->orWhere(function ($subQ) use ($tenant) {
                                $subQ->where('metric_name', 'patients')
                                     ->where('value', '>', $tenant->max_patients);
                            })
                            ->orWhere(function ($subQ) use ($tenant) {
                                $subQ->where('metric_name', 'storage_mb')
                                     ->where('value', '>', $tenant->max_storage_mb);
                            });
                        });
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('recorded_at', 'desc');
    }
}