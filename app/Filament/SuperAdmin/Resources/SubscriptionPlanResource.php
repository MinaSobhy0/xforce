<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Resources\Concerns\Translatable;

class SubscriptionPlanResource extends Resource
{
    use Translatable;

    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Subscription Plans';

    protected static ?string $navigationGroup = 'Plans & Modules';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('General')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),

                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required(),

                    Forms\Components\Textarea::make('description')
                        ->label('Description'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Toggle::make('is_featured'),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                ]),

            Forms\Components\Section::make('Pricing')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('price_monthly_minor')
                        ->label('Monthly Price (piasters)')
                        ->numeric()
                        ->required()
                        ->helperText('In minor units (1 EGP = 100 piasters)'),

                    Forms\Components\TextInput::make('price_yearly_minor')
                        ->label('Yearly Price (piasters)')
                        ->numeric()
                        ->required(),

                    Forms\Components\Select::make('currency')
                        ->options(['EGP' => 'EGP', 'SAR' => 'SAR', 'AED' => 'AED'])
                        ->default('EGP')
                        ->required(),

                    Forms\Components\TextInput::make('trial_days')
                        ->numeric()
                        ->default(14),
                ]),

            Forms\Components\Section::make('Hard Limits')
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('max_users')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_branches')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_patients')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_storage_mb')->label('Storage (MB)')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_equipment')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_products')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_treatments')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_api_calls_daily')->numeric()->placeholder('Unlimited'),
                ]),

            Forms\Components\Section::make('Monthly Limits')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('max_appointments_monthly')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_whatsapp_monthly')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_sms_monthly')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_emails_monthly')->numeric()->placeholder('Unlimited'),
                    Forms\Components\TextInput::make('max_campaign_recipients')->numeric()->placeholder('Unlimited'),
                ]),

            Forms\Components\Section::make('Overage Pricing')
                ->description('Charges applied when limits are exceeded (in piasters)')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('overage_appointment_minor')
                        ->label('Per Extra Appointment (piasters)')
                        ->numeric()
                        ->placeholder('0'),
                    Forms\Components\TextInput::make('overage_whatsapp_minor')
                        ->label('Per Extra WhatsApp (piasters)')
                        ->numeric()
                        ->placeholder('0'),
                    Forms\Components\TextInput::make('overage_sms_minor')
                        ->label('Per Extra SMS (piasters)')
                        ->numeric()
                        ->placeholder('0'),
                    Forms\Components\TextInput::make('overage_email_minor')
                        ->label('Per Extra Email (piasters)')
                        ->numeric()
                        ->placeholder('0'),
                    Forms\Components\TextInput::make('overage_storage_gb_minor')
                        ->label('Per Extra GB Storage (piasters)')
                        ->numeric()
                        ->placeholder('0'),
                ]),

            Forms\Components\Section::make('Features')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('allow_white_label'),
                    Forms\Components\Toggle::make('allow_custom_domain'),
                    Forms\Components\Toggle::make('allow_data_export')->default(true),
                    Forms\Components\Toggle::make('allow_api_access'),
                    Forms\Components\Toggle::make('has_priority_support'),
                    Forms\Components\TextInput::make('data_retention_days')->numeric()->default(365),
                    Forms\Components\TextInput::make('max_concurrent_sessions')->numeric(),
                ]),

            Forms\Components\Section::make('Included Modules')
                ->schema([
                    Forms\Components\CheckboxList::make('included_module_codes')
                        ->label('Select modules included in this plan')
                        ->options(fn() => \App\Models\Module::active()->ordered()->pluck('code', 'code'))
                        ->columns(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'enterprise' => 'success',
                        'professional' => 'info',
                        'starter' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('price_monthly_minor')
                    ->label('Monthly')
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_yearly_minor')
                    ->label('Yearly')
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('active_tenant_count')
                    ->label('Tenants')
                    ->getStateUsing(fn(SubscriptionPlan $record) => $record->active_tenant_count),

                Tables\Columns\TextColumn::make('mrr')
                    ->label('MRR')
                    ->getStateUsing(fn(SubscriptionPlan $record) => $record->mrr)
                    ->money('EGP', divideBy: 100),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function (SubscriptionPlan $record) {
                        $new = $record->replicate();
                        $new->code = $record->code . '_copy';
                        $new->name = $record->name . ' (Copy)';
                        $new->is_active = false;
                        $new->save();

                        \Filament\Notifications\Notification::make()
                            ->title('Plan duplicated')
                            ->body("Created new plan: {$new->name}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Archive Plan')
                    ->modalDescription(fn (SubscriptionPlan $record) =>
                        "This will deactivate the '{$record->name}' plan. Existing subscribers will keep their plan, but no new subscriptions will be allowed."
                    )
                    ->visible(fn (SubscriptionPlan $record) => $record->is_active)
                    ->action(function (SubscriptionPlan $record) {
                        $record->update(['is_active' => false]);

                        \Filament\Notifications\Notification::make()
                            ->title('Plan archived')
                            ->body("'{$record->name}' has been archived")
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SubscriptionPlan $record) => !$record->is_active)
                    ->action(function (SubscriptionPlan $record) {
                        $record->update(['is_active' => true]);

                        \Filament\Notifications\Notification::make()
                            ->title('Plan activated')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
