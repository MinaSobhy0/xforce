<?php

namespace Modules\Core\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $recordTitleAttribute = 'plan_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('plan_name')
                            ->label(__('Plan Name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'active' => __('Active'),
                                'cancelled' => __('Cancelled'),
                                'expired' => __('Expired'),
                                'pending' => __('Pending'),
                            ])
                            ->required()
                            ->default('active'),
                    ]),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('monthly_price')
                            ->label(__('Monthly Price'))
                            ->numeric()
                            ->prefix('EGP')
                            ->nullable(),

                        Forms\Components\TextInput::make('yearly_price')
                            ->label(__('Yearly Price'))
                            ->numeric()
                            ->prefix('EGP')
                            ->nullable(),

                        Forms\Components\Select::make('billing_cycle')
                            ->label(__('Billing Cycle'))
                            ->options([
                                'monthly' => __('Monthly'),
                                'yearly' => __('Yearly'),
                                'lifetime' => __('Lifetime'),
                            ])
                            ->required(),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label(__('Starts At'))
                            ->required()
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('Expires At'))
                            ->nullable(),
                    ]),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('max_users')
                            ->label(__('Max Users'))
                            ->numeric()
                            ->default(10)
                            ->minValue(1),

                        Forms\Components\TextInput::make('max_patients')
                            ->label(__('Max Patients'))
                            ->numeric()
                            ->default(1000)
                            ->minValue(10),

                        Forms\Components\TextInput::make('max_storage_mb')
                            ->label(__('Max Storage (MB)'))
                            ->numeric()
                            ->default(1024)
                            ->minValue(100),
                    ]),

                Forms\Components\CheckboxList::make('features')
                    ->label(__('Included Features'))
                    ->options([
                        'users' => __('User Management'),
                        'patients' => __('Patient Management'),
                        'appointments' => __('Appointment Scheduling'),
                        'treatments' => __('Treatment Catalog'),
                        'inventory' => __('Inventory Management'),
                        'billing' => __('Billing & Invoicing'),
                        'reports' => __('Reports & Analytics'),
                        'marketing' => __('Marketing Tools'),
                        'portal' => __('Patient Portal'),
                        'api' => __('API Access'),
                    ])
                    ->columns(2),

                Forms\Components\Textarea::make('notes')
                    ->label(__('Notes'))
                    ->nullable()
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('plan_name')
            ->columns([
                Tables\Columns\TextColumn::make('plan_name')
                    ->label(__('Plan'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('Status'))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'pending',
                        'danger' => ['cancelled', 'expired'],
                    ]),

                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label(__('Billing'))
                    ->badge(),

                Tables\Columns\TextColumn::make('monthly_price')
                    ->label(__('Monthly'))
                    ->prefix('EGP ')
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('yearly_price')
                    ->label(__('Yearly'))
                    ->prefix('EGP ')
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('Starts'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('Expires'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn($record) => $record?->expires_at?->isPast() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('max_users')
                    ->label(__('Users'))
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'active' => __('Active'),
                        'cancelled' => __('Cancelled'),
                        'expired' => __('Expired'),
                        'pending' => __('Pending'),
                    ]),

                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->label(__('Billing Cycle'))
                    ->options([
                        'monthly' => __('Monthly'),
                        'yearly' => __('Yearly'),
                        'lifetime' => __('Lifetime'),
                    ]),

                Tables\Filters\Filter::make('expired')
                    ->label(__('Expired'))
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<', now())),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('renew')
                    ->label(__('Renew'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('duration')
                            ->label(__('Duration'))
                            ->options([
                                '1_month' => __('1 Month'),
                                '3_months' => __('3 Months'),
                                '6_months' => __('6 Months'),
                                '1_year' => __('1 Year'),
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        $durations = [
                            '1_month' => 1,
                            '3_months' => 3,
                            '6_months' => 6,
                            '1_year' => 12,
                        ];

                        $months = $durations[$data['duration']];
                        $newExpiry = $record->expires_at && $record->expires_at->isFuture()
                            ? $record->expires_at->addMonths($months)
                            : now()->addMonths($months);

                        $record->update([
                            'expires_at' => $newExpiry,
                            'status' => 'active',
                        ]);

                        $this->notify('success', __('Subscription renewed until :date', ['date' => $newExpiry->format('Y-m-d')]));
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}