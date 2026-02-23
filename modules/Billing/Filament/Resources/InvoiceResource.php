<?php

namespace Modules\Billing\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Billing\Filament\Resources\InvoiceResource\Pages;
use Modules\Billing\Filament\Resources\InvoiceResource\RelationManagers;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\TaxRate;
use Modules\Patients\Models\Patient;
use Modules\Core\Models\Branch;
use Modules\Services\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InvoiceResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Invoice::class;

    protected static ?string $moduleCode = 'billing';

    protected static ?string $permissionKey = 'invoices';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::unpaid()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Invoice Details')
                            ->schema([
                                Forms\Components\Select::make('patient_id')
                                    ->label('Patient')
                                    ->relationship('patient', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn (Patient $record) => $record->display_name)
                                    ->searchable(['first_name', 'last_name', 'phone', 'code'])
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('first_name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('last_name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('phone')
                                            ->tel()
                                            ->required()
                                            ->maxLength(20),
                                    ]),

                                Forms\Components\Select::make('branch_id')
                                    ->label('Branch')
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => current_branch_id())
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),

                                Forms\Components\Select::make('type')
                                    ->options(Invoice::TYPES)
                                    ->default(Invoice::TYPE_STANDARD)
                                    ->required(),

                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->default(fn () => now()->addDays(config('billing.default_payment_terms_days', 0))),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Line Items')
                            ->schema([
                                Forms\Components\Repeater::make('lines')
                                    ->relationship()
                                    ->schema([
                                        Forms\Components\Select::make('service_id')
                                            ->label('Service')
                                            ->options(Service::query()->where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, \Livewire\Component $livewire) {
                                                if ($state) {
                                                    $service = Service::find($state);
                                                    if ($service) {
                                                        $branchId = data_get($livewire, 'data.branch_id') ?? current_branch_id();
                                                        $price = $branchId
                                                            ? $service->getPriceForBranch($branchId)
                                                            : $service->base_price_minor;
                                                        $set('description', $service->name);
                                                        // $price is in minor, convert to display value
                                                        $set('unit_price_minor', $price / 100);
                                                        $set('tax_rate', TaxRate::getDefault()?->rate ?? 14);
                                                    }
                                                }
                                            })
                                            ->columnSpan(['default' => 12, 'md' => 4]),

                                        Forms\Components\TextInput::make('description')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 12, 'md' => 8]),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->required()
                                            ->columnSpan(['default' => 4, 'md' => 2]),

                                        Forms\Components\TextInput::make('unit_price_minor')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true)
                                            ->prefix(current_currency())
                                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ((float) $state * 100) : 0)
                                            ->columnSpan(['default' => 8, 'md' => 3]),

                                        Forms\Components\Select::make('discount_type')
                                            ->label('Type')
                                            ->options([
                                                'fixed' => current_currency(),
                                                'percent' => '%',
                                            ])
                                            ->default('fixed')
                                            ->live()
                                            ->columnSpan(['default' => 4, 'md' => 2]),

                                        Forms\Components\TextInput::make('discount_minor')
                                            ->label('Discount')
                                            ->numeric()
                                            ->default(0)
                                            ->formatStateUsing(function ($state, Forms\Get $get) {
                                                if ($get('discount_type') === 'percent') {
                                                    return $state ?: 0;
                                                }
                                                return $state ? $state / 100 : 0;
                                            })
                                            ->dehydrateStateUsing(function ($state, Forms\Get $get) {
                                                if ($get('discount_type') === 'percent') {
                                                    return $state ? (int) $state : 0;
                                                }
                                                return $state ? (int) ($state * 100) : 0;
                                            })
                                            ->columnSpan(['default' => 4, 'md' => 2]),

                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->default(fn () => TaxRate::getDefault()?->rate ?? 14)
                                            ->suffix('%')
                                            ->columnSpan(['default' => 4, 'md' => 3]),
                                    ])
                                    ->columns(12)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add Line Item')
                                    ->reorderable()
                                    ->reorderableWithButtons()
                                    ->cloneable()
                                    ->live(onBlur: true)
                                    ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Summary')
                            ->schema([
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $subtotal = 0;
                                        foreach ($lines as $line) {
                                            $qty = (float) ($line['quantity'] ?? 0);
                                            $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                            $lineSubtotal = $qty * $price;

                                            // Apply line discount
                                            $discountType = $line['discount_type'] ?? 'fixed';
                                            $discountValue = (float) ($line['discount_minor'] ?? 0);
                                            if ($discountType === 'percent') {
                                                $lineSubtotal -= $lineSubtotal * $discountValue / 100;
                                            } else {
                                                $lineSubtotal -= $discountValue * 100;
                                            }
                                            $subtotal += max(0, $lineSubtotal);
                                        }
                                        return format_money((int) $subtotal);
                                    }),

                                Forms\Components\Select::make('discount_type')
                                    ->options([
                                        'fixed' => 'Fixed Amount',
                                        'percent' => 'Percentage',
                                    ])
                                    ->default('fixed')
                                    ->live(),

                                Forms\Components\TextInput::make('discount_minor')
                                    ->label('Discount Value')
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->formatStateUsing(function ($state, Forms\Get $get) {
                                        if ($get('discount_type') === 'percent') {
                                            return $state ?: 0;
                                        }
                                        return $state ? $state / 100 : 0;
                                    })
                                    ->dehydrateStateUsing(function ($state, Forms\Get $get) {
                                        if ($get('discount_type') === 'percent') {
                                            return $state ? (int) $state : 0;
                                        }
                                        return $state ? (int) ($state * 100) : 0;
                                    })
                                    ->prefix(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? null : current_currency())
                                    ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? '%' : null),

                                Forms\Components\Placeholder::make('tax_display')
                                    ->label('Tax')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $tax = 0;
                                        foreach ($lines as $line) {
                                            $qty = (float) ($line['quantity'] ?? 0);
                                            $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                            $lineSubtotal = $qty * $price;

                                            // Apply line discount first
                                            $discountType = $line['discount_type'] ?? 'fixed';
                                            $discountValue = (float) ($line['discount_minor'] ?? 0);
                                            if ($discountType === 'percent') {
                                                $lineSubtotal -= $lineSubtotal * $discountValue / 100;
                                            } else {
                                                $lineSubtotal -= $discountValue * 100;
                                            }
                                            $lineSubtotal = max(0, $lineSubtotal);

                                            // Calculate tax
                                            $taxRate = (float) ($line['tax_rate'] ?? 0);
                                            $tax += $lineSubtotal * $taxRate / 100;
                                        }
                                        return format_money((int) $tax);
                                    }),

                                Forms\Components\Placeholder::make('total_display')
                                    ->label('Total')
                                    ->content(function (Forms\Get $get) {
                                        $lines = $get('lines') ?? [];
                                        $subtotal = 0;
                                        $tax = 0;

                                        foreach ($lines as $line) {
                                            $qty = (float) ($line['quantity'] ?? 0);
                                            $price = (float) ($line['unit_price_minor'] ?? 0) * 100;
                                            $lineSubtotal = $qty * $price;

                                            // Apply line discount
                                            $discountType = $line['discount_type'] ?? 'fixed';
                                            $discountValue = (float) ($line['discount_minor'] ?? 0);
                                            if ($discountType === 'percent') {
                                                $lineSubtotal -= $lineSubtotal * $discountValue / 100;
                                            } else {
                                                $lineSubtotal -= $discountValue * 100;
                                            }
                                            $lineSubtotal = max(0, $lineSubtotal);
                                            $subtotal += $lineSubtotal;

                                            // Calculate tax
                                            $taxRate = (float) ($line['tax_rate'] ?? 0);
                                            $tax += $lineSubtotal * $taxRate / 100;
                                        }

                                        // Apply invoice-level discount
                                        $invoiceDiscountType = $get('discount_type') ?? 'fixed';
                                        $invoiceDiscountValue = (float) ($get('discount_minor') ?? 0);
                                        $invoiceDiscount = 0;
                                        if ($invoiceDiscountType === 'percent') {
                                            $invoiceDiscount = $subtotal * $invoiceDiscountValue / 100;
                                        } else {
                                            $invoiceDiscount = $invoiceDiscountValue * 100;
                                        }

                                        $total = max(0, $subtotal + $tax - $invoiceDiscount);
                                        return format_money((int) $total);
                                    })
                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                            ]),

                        Forms\Components\Section::make('Notes')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label('Customer Notes')
                                    ->rows(2),

                                Forms\Components\Textarea::make('internal_notes')
                                    ->label('Internal Notes')
                                    ->rows(2),
                            ])
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_minor')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->sortable()
                    ->color(fn (Invoice $record) => $record->isPaid() ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('remaining_minor')
                    ->label('Remaining')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => Invoice::STATUS_DRAFT,
                        'info' => Invoice::STATUS_ISSUED,
                        'warning' => Invoice::STATUS_PARTIALLY_PAID,
                        'success' => Invoice::STATUS_PAID,
                        'danger' => fn ($state) => in_array($state, [Invoice::STATUS_OVERDUE, Invoice::STATUS_CANCELLED]),
                    ]),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn (Invoice $record) => $record->is_overdue ? 'danger' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Invoice::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name'),

                Tables\Filters\SelectFilter::make('type')
                    ->options(Invoice::TYPES),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn (Builder $query) => $query->overdue()),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Invoice $record) => $record->isEditable()),

                    Tables\Actions\Action::make('issue')
                        ->label('Issue Invoice')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->visible(fn (Invoice $record) => $record->isDraft())
                        ->action(fn (Invoice $record) => $record->issue()),

                    Tables\Actions\Action::make('record_payment')
                        ->label('Record Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Invoice $record) => $record->canRecordPayment())
                        ->url(fn (Invoice $record) => route('filament.tenant.resources.invoices.record-payment', $record)),

                    Tables\Actions\Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Invoice $record) => $record->canTransitionTo(Invoice::STATUS_CANCELLED))
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Cancellation Reason')
                                ->required(),
                        ])
                        ->action(fn (Invoice $record, array $data) => $record->cancel($data['reason'])),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => false), // Disable bulk delete for invoices
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label('Invoice #')
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => Invoice::STATUS_COLORS[$state] ?? 'gray'),

                        Infolists\Components\TextEntry::make('type')
                            ->formatStateUsing(fn ($state) => Invoice::TYPES[$state] ?? $state),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Customer')
                    ->schema([
                        Infolists\Components\TextEntry::make('patient.full_name')
                            ->label('Patient'),

                        Infolists\Components\TextEntry::make('patient.phone')
                            ->label('Phone'),

                        Infolists\Components\TextEntry::make('branch.name')
                            ->label('Branch'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Amounts')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal_minor')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('discount_minor')
                            ->label('Discount')
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('tax_minor')
                            ->label('Tax')
                            ->formatStateUsing(fn ($state) => format_money($state)),

                        Infolists\Components\TextEntry::make('total_minor')
                            ->label('Total')
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->weight(FontWeight::Bold),

                        Infolists\Components\TextEntry::make('paid_minor')
                            ->label('Paid')
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->color('success'),

                        Infolists\Components\TextEntry::make('remaining_minor')
                            ->label('Remaining')
                            ->formatStateUsing(fn ($state) => format_money($state))
                            ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Dates')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('issued_at')
                            ->label('Issued')
                            ->dateTime()
                            ->placeholder('Not issued'),

                        Infolists\Components\TextEntry::make('due_date')
                            ->label('Due Date')
                            ->date()
                            ->placeholder('No due date'),

                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Paid')
                            ->dateTime()
                            ->placeholder('Not paid'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Customer Notes')
                            ->placeholder('No notes'),

                        Infolists\Components\TextEntry::make('internal_notes')
                            ->label('Internal Notes')
                            ->placeholder('No internal notes'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
            'record-payment' => Pages\RecordPayment::route('/{record}/record-payment'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['patient', 'branch']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Patient' => $record->patient?->full_name,
            'Total' => format_money($record->total_minor),
        ];
    }
}
