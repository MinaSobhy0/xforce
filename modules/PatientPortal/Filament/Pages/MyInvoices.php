<?php

namespace Modules\PatientPortal\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Models\Invoice;

class MyInvoices extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'patientportal::filament.pages.my-invoices';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('patientportal::portal.my_invoices');
    }

    public function getTitle(): string
    {
        return __('patientportal::portal.my_invoices');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('patientportal::portal.invoice_number'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('patientportal::portal.date'))
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('appointment.treatment.name')
                    ->label(__('patientportal::portal.treatment'))
                    ->formatStateUsing(function ($state) {
                        if (is_array(json_decode($state, true))) {
                            return json_decode($state, true)[app()->getLocale()] ?? json_decode($state, true)['en'] ?? $state;
                        }
                        return $state;
                    }),

                TextColumn::make('total_minor')
                    ->label(__('patientportal::portal.total'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' EGP')
                    ->sortable(),

                TextColumn::make('paid_amount_minor')
                    ->label(__('patientportal::portal.paid'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' EGP'),

                TextColumn::make('balance')
                    ->label(__('patientportal::portal.balance'))
                    ->getStateUsing(fn (Invoice $record) => $record->total_minor - $record->paid_amount_minor)
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' EGP')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                BadgeColumn::make('status')
                    ->label(__('patientportal::portal.status'))
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'sent',
                        'info' => 'partially_paid',
                        'success' => 'paid',
                        'danger' => ['overdue', 'cancelled'],
                    ])
                    ->formatStateUsing(fn ($state) => __('billing::billing.invoice_statuses.' . $state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'sent' => __('billing::billing.invoice_statuses.sent'),
                        'partially_paid' => __('billing::billing.invoice_statuses.partially_paid'),
                        'paid' => __('billing::billing.invoice_statuses.paid'),
                        'overdue' => __('billing::billing.invoice_statuses.overdue'),
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label(__('patientportal::portal.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (Invoice $record) => ViewInvoice::getUrl(['record' => $record->id])),

                Action::make('pay')
                    ->label(__('patientportal::portal.pay_now'))
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn (Invoice $record) => in_array($record->status, ['sent', 'partially_paid', 'overdue']))
                    ->url(fn (Invoice $record) => PayInvoice::getUrl(['record' => $record->id])),

                Action::make('download')
                    ->label(__('patientportal::portal.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Invoice $record) => $this->downloadInvoice($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('patientportal::portal.no_invoices'))
            ->emptyStateDescription(__('patientportal::portal.no_invoices_desc'))
            ->emptyStateIcon('heroicon-o-document-text');
    }

    protected function getTableQuery(): Builder
    {
        $patient = Auth::guard('patient')->user();

        return Invoice::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue'])
            ->with(['appointment.treatment']);
    }

    protected function downloadInvoice(Invoice $invoice)
    {
        // Generate PDF and return download response
        // TODO: Implement PDF generation
        return response()->download(
            storage_path('app/invoices/' . $invoice->invoice_number . '.pdf')
        );
    }
}
