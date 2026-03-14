<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Pages;
use App\Models\PlatformInvoice;
use Modules\Core\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class PlatformInvoiceResource extends Resource
{
    protected static ?string $model = PlatformInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Platform Invoices';

    protected static ?string $navigationGroup = 'Financials';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $overdue = static::getModel()::where('status', 'overdue')->count();
        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Invoice Details')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label('Clinic')
                        ->options(fn() => Tenant::pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            if (!$state) {
                                return;
                            }

                            $tenant = Tenant::with(['plan', 'activeAddOns'])->find($state);
                            if (!$tenant) {
                                return;
                            }

                            $country = $tenant->country ?? 'EG';
                            $currency = $tenant->getCurrency();
                            $lineItems = [];

                            // Plan charge (country-specific) - in EGP for display
                            $planChargeMinor = 0;
                            if ($tenant->plan) {
                                $planPrice = $tenant->plan->getPriceForCountry($country);
                                $planChargeMinor = $planPrice['amount_minor'] ?? 0;
                                $lineItems[] = [
                                    'type' => 'plan',
                                    'description' => $tenant->plan->name . ' (Monthly)',
                                    'amount_minor' => $planChargeMinor,
                                ];
                            }

                            // Add-on charges
                            $addonChargesMinor = 0;
                            foreach ($tenant->activeAddOns as $addon) {
                                // Get price from pivot or calculate from add-on
                                $price = $addon->pivot->price ?? null;
                                if (!$price) {
                                    $priceData = $addon->getPriceForCountry($country);
                                    $price = (int) ($priceData['amount'] * 100); // Convert to minor
                                }
                                $addonChargesMinor += $price;
                                $lineItems[] = [
                                    'type' => 'addon',
                                    'description' => $addon->name,
                                    'amount_minor' => $price,
                                ];
                            }

                            // Set period dates (current month)
                            $periodStart = now()->startOfMonth();
                            $periodEnd = now()->endOfMonth();
                            $dueDate = now()->addDays(15);

                            // Calculate totals (in minor/piasters)
                            $subtotalMinor = $planChargeMinor + $addonChargesMinor;

                            // Get tax rate from plan's country-specific pricing (as percentage)
                            $taxRatePercent = $tenant->plan
                                ? $tenant->plan->getTaxRateForCountry($country)
                                : (\App\Models\SubscriptionPlan::COUNTRIES[$country]['default_tax'] ?? 14);
                            $taxRate = $taxRatePercent / 100; // Convert percentage to decimal
                            $taxMinor = (int) round($subtotalMinor * $taxRate);
                            $totalMinor = $subtotalMinor + $taxMinor;

                            // Set form values (in main currency)
                            $set('plan_code', $tenant->plan?->code);
                            $set('plan_charge', round($planChargeMinor / 100, 2));
                            $set('addon_charges', round($addonChargesMinor / 100, 2));
                            $set('overage_charges', 0);
                            $set('discount', 0);
                            $set('subtotal', round($subtotalMinor / 100, 2));
                            $set('tax_rate', round($taxRatePercent, 2)); // Percentage from plan (e.g., 14 for 14%)
                            $set('tax', round($taxMinor / 100, 2));
                            $set('total', round($totalMinor / 100, 2));
                            $set('currency', $currency);
                            $set('period_start', $periodStart->format('Y-m-d'));
                            $set('period_end', $periodEnd->format('Y-m-d'));
                            $set('due_date', $dueDate->format('Y-m-d'));
                            $set('line_items', $lineItems);
                        }),

                    Forms\Components\TextInput::make('number')
                        ->label('Invoice Number')
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\DatePicker::make('period_start')
                        ->label('Period Start')
                        ->required(),

                    Forms\Components\DatePicker::make('period_end')
                        ->label('Period End')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options(PlatformInvoice::STATUSES)
                        ->default('pending')
                        ->required(),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Due Date'),
                ]),

            Forms\Components\Section::make('Charges')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('currency')
                        ->label('Currency')
                        ->default('EGP')
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('plan_charge')
                        ->label('Plan Charge')
                        ->numeric()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculateTotals($get, $set)),

                    Forms\Components\TextInput::make('addon_charges')
                        ->label('Add-on Charges')
                        ->numeric()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculateTotals($get, $set)),

                    Forms\Components\TextInput::make('overage_charges')
                        ->label('Overage Charges')
                        ->numeric()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculateTotals($get, $set)),

                    Forms\Components\TextInput::make('discount')
                        ->label('Discount')
                        ->numeric()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculateTotals($get, $set)),

                    Forms\Components\TextInput::make('discount_code')
                        ->label('Discount Code'),

                    Forms\Components\TextInput::make('tax_rate')
                        ->label('Tax Rate (%)')
                        ->numeric()
                        ->default(14)
                        ->step(1)
                        ->suffix('%')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculateTotals($get, $set)),
                ]),

            Forms\Components\Section::make('Totals')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('subtotal')
                        ->label('Subtotal')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('tax')
                        ->label('Tax')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('total')
                        ->label('Total')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Hidden::make('plan_code'),
                ]),

            Forms\Components\Section::make('Line Items')
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('line_items')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->options([
                                    'plan' => 'Plan',
                                    'addon' => 'Add-on',
                                    'overage' => 'Overage',
                                    'other' => 'Other',
                                ])
                                ->required(),
                            Forms\Components\TextInput::make('description')
                                ->required(),
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount')
                                ->numeric()
                                ->required(),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {
                            $data['amount'] = ($data['amount_minor'] ?? 0) / 100;
                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                            $data['amount_minor'] = ($data['amount'] ?? 0) * 100;
                            unset($data['amount']);
                            return $data;
                        }),
                ]),

            Forms\Components\Section::make('Payment')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('payment_method')
                        ->label('Payment Method'),

                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Payment Reference'),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Paid At'),

                    Forms\Components\Textarea::make('notes')
                        ->columnSpanFull(),
                ]),
        ]);
    }


    protected static function recalculateTotals(Get $get, Set $set): void
    {
        $planCharge = (float) ($get('plan_charge') ?? 0);
        $addonCharges = (float) ($get('addon_charges') ?? 0);
        $overageCharges = (float) ($get('overage_charges') ?? 0);
        $discount = (float) ($get('discount') ?? 0);
        $taxRatePercent = (float) ($get('tax_rate') ?? 14);

        $subtotal = $planCharge + $addonCharges + $overageCharges - $discount;
        $tax = round($subtotal * ($taxRatePercent / 100), 2);
        $total = $subtotal + $tax;

        $set('subtotal', $subtotal);
        $set('tax', $tax);
        $set('total', $total);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Clinic')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->date('M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('plan_charge_minor')
                    ->label('Plan')
                    ->money('EGP', divideBy: 100),

                Tables\Columns\TextColumn::make('addon_charges_minor')
                    ->label('Add-ons')
                    ->money('EGP', divideBy: 100),

                Tables\Columns\TextColumn::make('overage_charges_minor')
                    ->label('Overage')
                    ->money('EGP', divideBy: 100)
                    ->color(fn(int $state) => $state > 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->money('EGP', divideBy: 100)
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'overdue' => 'danger',
                        'refunded' => 'info',
                        'draft' => 'gray',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid')
                    ->date()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PlatformInvoice::STATUSES),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn(Builder $query) => $query->whereMonth('period_start', now()->month)),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn(Builder $query) => $query->where('status', 'overdue')),
            ])
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => in_array($record->status, ['pending', 'overdue']))
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markAsPaid($data['payment_reference'] ?? null);
                        \Filament\Notifications\Notification::make()
                            ->title('Invoice marked as paid')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('sendReminder')
                    ->label('Send Reminder')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->visible(fn($record) => in_array($record->status, ['pending', 'overdue']))
                    ->action(function ($record) {
                        \Filament\Notifications\Notification::make()
                            ->title('Payment reminder sent')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('sendReminders')
                    ->label('Send Payment Reminders')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        \Filament\Notifications\Notification::make()
                            ->title("Reminders sent to {$records->count()} clinics")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\BulkAction::make('markOverdue')
                    ->label('Mark as Overdue')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $records->each(fn($record) => $record->update(['status' => 'overdue']));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformInvoices::route('/'),
            'create' => Pages\CreatePlatformInvoice::route('/create'),
            'view' => Pages\ViewPlatformInvoice::route('/{record}'),
            'edit' => Pages\EditPlatformInvoice::route('/{record}/edit'),
        ];
    }
}
