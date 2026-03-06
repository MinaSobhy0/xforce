<?php

namespace Modules\Billing\Filament\Resources\InvoiceResource\Pages;

use Modules\Billing\Filament\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\TaxRate;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Patients\Models\Patient;
use Modules\Services\Models\Service;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Notifications\Notification;

class ViewInvoice extends BaseViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static string $view = 'filament.resources.invoice-resource.pages.view-invoice';

    public bool $isEditing = false;

    public ?array $invoiceData = [];
    public ?array $linesData = [];

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->loadFormData();
    }

    protected function loadFormData(): void
    {
        // Load invoice header data
        $this->invoiceData = [
            'patient_id' => $this->record->patient_id,
            'branch_id' => $this->record->branch_id,
            'type' => $this->record->type,
            'due_date' => $this->record->due_date,
            'discount_type' => $this->record->discount_type ?? 'fixed',
            'discount_value' => $this->record->discount_type === 'percent'
                ? $this->record->discount_minor
                : ($this->record->discount_minor / 100),
            'notes' => $this->record->notes,
            'internal_notes' => $this->record->internal_notes,
        ];

        // Load lines data
        $this->linesData = [
            'lines' => $this->record->lines()->orderBy('sort_order')->get()->map(function ($line) {
                return [
                    'id' => $line->id,
                    'line_type' => $line->line_type ?? InvoiceLine::LINE_TYPE_SERVICE,
                    'service_id' => $line->service_id,
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price_minor / 100,
                    'discount_type' => $line->discount_type ?? 'fixed',
                    'discount' => $line->discount_type === 'percent'
                        ? $line->discount_minor
                        : ($line->discount_minor / 100),
                    'tax_rates' => $line->tax_rates ?? [],
                ];
            })->toArray(),
        ];
    }

    public function invoiceForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label(__('billing::billing.fields.patient'))
                            ->options(fn () => Patient::query()->limit(50)->get()->mapWithKeys(fn ($p) => [$p->id => $p->display_name]))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Patient::where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%")
                                ->orWhere('phone', 'ilike', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($p) => [$p->id => $p->display_name]))
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label(__('billing::billing.fields.branch'))
                            ->options(fn () => \Modules\Core\Models\Branch::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled(fn () => current_branch_id() !== null)
                            ->dehydrated(),

                        Forms\Components\Select::make('type')
                            ->label(__('billing::billing.fields.type'))
                            ->options(Invoice::TYPES)
                            ->required(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label(__('billing::billing.fields.due_date')),
                    ]),

                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->label(__('billing::billing.fields.discount_type'))
                            ->options([
                                'fixed' => __('billing::billing.discount_types.fixed'),
                                'percent' => __('billing::billing.discount_types.percent'),
                            ])
                            ->default('fixed')
                            ->live(),

                        Forms\Components\TextInput::make('discount_value')
                            ->label(__('billing::billing.fields.discount'))
                            ->numeric()
                            ->default(0),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('billing::billing.fields.customer_notes'))
                            ->rows(1),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label(__('billing::billing.fields.internal_notes'))
                            ->rows(1),
                    ]),
            ])
            ->statePath('invoiceData');
    }

    public function linesForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Repeater::make('lines')
                    ->hiddenLabel()
                    ->schema([
                        Forms\Components\Select::make('line_type')
                            ->hiddenLabel()
                            ->placeholder(__('billing::billing.fields.line_type'))
                            ->options([
                                InvoiceLine::LINE_TYPE_SERVICE => __('billing::billing.line_types.service'),
                                InvoiceLine::LINE_TYPE_PRODUCT => __('billing::billing.line_types.product'),
                                InvoiceLine::LINE_TYPE_PACKAGE => __('billing::billing.line_types.package'),
                                InvoiceLine::LINE_TYPE_OTHER => __('billing::billing.line_types.other'),
                            ])
                            ->default(InvoiceLine::LINE_TYPE_SERVICE)
                            ->required()
                            ->live()
                            ->selectablePlaceholder(false)
                            ->columnSpan(1),

                        Forms\Components\Select::make('service_id')
                            ->hiddenLabel()
                            ->placeholder(__('billing::billing.fields.service'))
                            ->options(Service::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Forms\Get $get) => $get('line_type') === InvoiceLine::LINE_TYPE_SERVICE)
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $service = Service::find($state);
                                    if ($service) {
                                        $set('description', $service->name);
                                        $set('unit_price', $service->base_price_minor / 100);
                                        $set('account_id', $service->account_id ?? ChartOfAccount::where('type', ChartOfAccount::TYPE_INCOME)->where('is_active', true)->first()?->id);
                                        $defaultTax = TaxRate::getDefault(TaxRate::TYPE_SALES);
                                        $set('tax_rates', $defaultTax ? [(string) $defaultTax->rate] : ['14']);
                                    }
                                }
                            })
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('description')
                            ->hiddenLabel()
                            ->placeholder(__('billing::billing.fields.description'))
                            ->required()
                            ->columnSpan(fn (Forms\Get $get) => $get('line_type') === InvoiceLine::LINE_TYPE_SERVICE ? 2 : 4),

                        Forms\Components\Select::make('account_id')
                            ->hiddenLabel()
                            ->placeholder(__('billing::billing.fields.account'))
                            ->options(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_INCOME)
                                ->where('is_active', true)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn ($a) => [$a->id => "[{$a->code}] " . $a->getTranslation('name', app()->getLocale())]))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => ChartOfAccount::where('type', ChartOfAccount::TYPE_INCOME)->where('is_active', true)->first()?->id)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('quantity')
                            ->hiddenLabel()
                            ->placeholder(__('billing::billing.fields.quantity'))
                            ->numeric()
                            ->default(1)
                            ->minValue(0.01)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('unit_price')
                            ->hiddenLabel()
                            ->placeholder(__('billing::billing.fields.unit_price'))
                            ->numeric()
                            ->required()
                            ->suffix(current_currency())
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('discount')
                            ->hiddenLabel()
                            ->placeholder(__('billing::billing.fields.discount'))
                            ->numeric()
                            ->default(0)
                            ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? '%' : current_currency())
                            ->columnSpan(1),

                        Forms\Components\ToggleButtons::make('discount_type')
                            ->hiddenLabel()
                            ->options([
                                'fixed' => current_currency(),
                                'percent' => '%',
                            ])
                            ->default('fixed')
                            ->inline()
                            ->live()
                            ->grouped()
                            ->columnSpan(1),

                        Forms\Components\Select::make('tax_rates')
                            ->hiddenLabel()
                            ->placeholder(__('billing::billing.fields.taxes'))
                            ->multiple()
                            ->options(function () {
                                return TaxRate::where('is_active', true)
                                    ->where('type', TaxRate::TYPE_SALES)
                                    ->orderByDesc('rate')
                                    ->get()
                                    ->mapWithKeys(fn ($t) => [
                                        (string) $t->rate => $t->rate . '%'
                                    ]);
                            })
                            ->default(function () {
                                $default = TaxRate::getDefault(TaxRate::TYPE_SALES);
                                return $default ? [(string) $default->rate] : ['14'];
                            })
                            ->columnSpan(1),

                        Forms\Components\Hidden::make('id'),
                    ])
                    ->columns(12)
                    ->reorderable()
                    ->reorderableWithDragAndDrop()
                    ->addActionLabel(__('billing::billing.actions.add_line_item'))
                    ->defaultItems(0),
            ])
            ->statePath('linesData');
    }

    protected function getForms(): array
    {
        return [
            'form',
            'invoiceForm',
            'linesForm',
        ];
    }

    public function enterEditMode(): void
    {
        $this->isEditing = true;
        $this->loadFormData();
    }

    public function cancelEdit(): void
    {
        $this->isEditing = false;
        $this->loadFormData();
    }

    public function addLine(): void
    {
        $defaultAccount = ChartOfAccount::where('type', ChartOfAccount::TYPE_INCOME)
            ->where('is_active', true)
            ->first();
        $defaultTax = TaxRate::getDefault(TaxRate::TYPE_SALES);

        $this->linesData['lines'][] = [
            'id' => null,
            'line_type' => InvoiceLine::LINE_TYPE_SERVICE,
            'service_id' => null,
            'account_id' => $defaultAccount?->id,
            'description' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'discount_type' => 'fixed',
            'discount' => 0,
            'tax_rates' => $defaultTax ? [(string) $defaultTax->rate] : ['14'],
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->linesData['lines'][$index]);
        $this->linesData['lines'] = array_values($this->linesData['lines']);
    }

    public function reorderLines(array $order): void
    {
        $lines = $this->linesData['lines'];
        $reordered = [];
        foreach ($order as $index) {
            if (isset($lines[$index])) {
                $reordered[] = $lines[$index];
            }
        }
        $this->linesData['lines'] = $reordered;
    }

    public function save(): void
    {
        // Save invoice header
        $invoiceData = $this->invoiceForm->getState();

        $this->record->update([
            'patient_id' => $invoiceData['patient_id'],
            'branch_id' => $invoiceData['branch_id'] ?? $this->record->branch_id,
            'type' => $invoiceData['type'],
            'due_date' => $invoiceData['due_date'] ?? null,
            'discount_type' => $invoiceData['discount_type'] ?? 'fixed',
            'discount_minor' => ($invoiceData['discount_type'] ?? 'fixed') === 'percent'
                ? (int) ($invoiceData['discount_value'] ?? 0)
                : (int) (($invoiceData['discount_value'] ?? 0) * 100),
            'notes' => $invoiceData['notes'] ?? null,
            'internal_notes' => $invoiceData['internal_notes'] ?? null,
        ]);

        // Save lines (using direct property since we use wire:model in custom table)
        $linesData = $this->linesData;

        $existingIds = $this->record->lines->pluck('id')->toArray();
        $submittedIds = collect($linesData['lines'] ?? [])->pluck('id')->filter()->toArray();

        // Delete removed lines
        $toDelete = array_diff($existingIds, $submittedIds);
        if (!empty($toDelete)) {
            InvoiceLine::whereIn('id', $toDelete)->delete();
        }

        // Update or create lines
        $sortOrder = 0;
        foreach ($linesData['lines'] ?? [] as $lineData) {
            $sortOrder++;

            $saveData = [
                'invoice_id' => $this->record->id,
                'line_type' => $lineData['line_type'] ?? InvoiceLine::LINE_TYPE_SERVICE,
                'service_id' => $lineData['line_type'] === InvoiceLine::LINE_TYPE_SERVICE ? ($lineData['service_id'] ?? null) : null,
                'account_id' => $lineData['account_id'] ?? ChartOfAccount::where('type', ChartOfAccount::TYPE_INCOME)->where('is_active', true)->first()?->id,
                'description' => $lineData['description'],
                'quantity' => $lineData['quantity'],
                'unit_price_minor' => (int) (($lineData['unit_price'] ?? 0) * 100),
                'discount_type' => $lineData['discount_type'] ?? 'fixed',
                'discount_minor' => ($lineData['discount_type'] ?? 'fixed') === 'percent'
                    ? (int) ($lineData['discount'] ?? 0)
                    : (int) (($lineData['discount'] ?? 0) * 100),
                'tax_rates' => $lineData['tax_rates'] ?? [],
                'sort_order' => $sortOrder,
            ];

            if (!empty($lineData['id'])) {
                $line = InvoiceLine::find($lineData['id']);
                if ($line) {
                    $line->update($saveData);
                    $line->calculateTotals();
                    $line->save();
                }
            } else {
                $line = InvoiceLine::create($saveData);
                $line->calculateTotals();
                $line->save();
            }
        }

        $this->record->refresh();
        $this->record->recalculateTotals();

        $this->isEditing = false;
        $this->loadFormData();

        Notification::make()
            ->title(__('filament-actions::edit.single.notifications.saved.title'))
            ->success()
            ->send();
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getViewHeaderActions(): array
    {
        return [
            // Edit button - enters edit mode
            Actions\Action::make('edit')
                ->label(__('filament-actions::edit.single.label'))
                ->icon('heroicon-m-pencil-square')
                ->visible(fn () => $this->record->isEditable() && !$this->isEditing)
                ->action(fn () => $this->enterEditMode()),

            // Save button - visible in edit mode
            Actions\Action::make('save')
                ->label(__('filament-actions::edit.single.modal.actions.save.label'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn () => $this->isEditing)
                ->action(fn () => $this->save()),

            // Cancel button - visible in edit mode
            Actions\Action::make('cancel_edit')
                ->label(__('filament-actions::modal.actions.cancel.label'))
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn () => $this->isEditing)
                ->action(fn () => $this->cancelEdit()),

            Actions\Action::make('issue')
                ->label(__('billing::billing.actions.issue'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription(__('billing::billing.messages.issue_confirmation'))
                ->visible(fn () => $this->record->isDraft() && !$this->isEditing)
                ->action(function () {
                    $this->record->issue();
                    Notification::make()
                        ->title(__('billing::billing.messages.invoice_issued'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('record_payment')
                ->label(__('billing::billing.actions.record_payment'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->canRecordPayment() && !$this->isEditing)
                ->url(fn () => $this->getResource()::getUrl('record-payment', ['record' => $this->record])),

            Actions\Action::make('print')
                ->label(__('billing::billing.actions.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => !$this->isEditing)
                ->url(fn () => '#')
                ->openUrlInNewTab(),

            Actions\Action::make('cancel')
                ->label(__('billing::billing.actions.cancel_short'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('billing::billing.messages.cancel_confirmation'))
                ->visible(fn () => $this->record->canTransitionTo(Invoice::STATUS_CANCELLED) && !$this->isEditing)
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label(__('billing::billing.fields.cancellation_reason'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->cancel($data['reason']);
                    Notification::make()
                        ->title(__('billing::billing.messages.invoice_cancelled'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reset_to_draft')
                ->label(__('billing::billing.actions.reset_to_draft'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(__('billing::billing.messages.reset_to_draft_confirmation'))
                ->visible(fn () => $this->record->canResetToDraft() && !$this->isEditing)
                ->action(function () {
                    if ($this->record->resetToDraft()) {
                        Notification::make()
                            ->title(__('billing::billing.messages.invoice_reset_to_draft'))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('billing::billing.messages.cannot_reset_to_draft'))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
