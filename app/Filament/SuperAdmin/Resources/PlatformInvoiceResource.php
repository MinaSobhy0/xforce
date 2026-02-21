<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Pages;
use App\Models\PlatformInvoice;
use Modules\Core\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
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
                        ->required(),

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
                    Forms\Components\TextInput::make('plan_charge_minor')
                        ->label('Plan Charge (piasters)')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('addon_charges_minor')
                        ->label('Add-on Charges (piasters)')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('overage_charges_minor')
                        ->label('Overage Charges (piasters)')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('discount_minor')
                        ->label('Discount (piasters)')
                        ->numeric()
                        ->default(0),

                    Forms\Components\TextInput::make('discount_code')
                        ->label('Discount Code'),

                    Forms\Components\TextInput::make('tax_rate')
                        ->label('Tax Rate')
                        ->numeric()
                        ->default(0.14)
                        ->step(0.01),
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
