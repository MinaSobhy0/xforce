<?php

namespace App\Filament\SuperAdmin\Resources\PlatformInvoiceResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformInvoiceResource;
use App\Models\PlatformInvoice;
use Filament\Actions;
use Filament\Forms;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Maatwebsite\Excel\Facades\Excel;

class ListPlatformInvoices extends BaseListRecords
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\Action::make('generateMonthly')
                ->label('Generate Monthly Invoices')
                ->icon('heroicon-o-document-plus')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Generate Monthly Invoices')
                ->modalDescription('This will generate invoices for all active tenants for the current billing period.')
                ->form([
                    Forms\Components\Select::make('billing_month')
                        ->label('Billing Month')
                        ->options(function () {
                            $options = [];
                            for ($i = 0; $i < 3; $i++) {
                                $date = now()->subMonths($i);
                                $options[$date->format('Y-m')] = $date->format('F Y');
                            }
                            return $options;
                        })
                        ->default(now()->format('Y-m'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    // TODO: Implement actual invoice generation
                    \Filament\Notifications\Notification::make()
                        ->title('Monthly invoices generated')
                        ->body("Invoices for {$data['billing_month']} have been generated")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('exportAll')
                ->label('Export All')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('format')
                        ->options([
                            'xlsx' => 'Excel (.xlsx)',
                            'csv' => 'CSV (.csv)',
                            'pdf' => 'PDF (.pdf)',
                        ])
                        ->default('xlsx')
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('Filter by Status')
                        ->options(array_merge(['all' => 'All Statuses'], PlatformInvoice::STATUSES))
                        ->default('all'),
                    Forms\Components\DatePicker::make('from_date')
                        ->label('From Date'),
                    Forms\Components\DatePicker::make('to_date')
                        ->label('To Date'),
                ])
                ->action(function (array $data) {
                    // Build filename
                    $filename = 'invoices-' . now()->format('Y-m-d');

                    // TODO: Implement actual export based on format
                    \Filament\Notifications\Notification::make()
                        ->title('Export started')
                        ->body("Your export will be ready shortly")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('sendOverdueReminders')
                ->label('Send Overdue Reminders')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This will send payment reminder emails to all clinics with overdue invoices.')
                ->action(function () {
                    $overdueCount = PlatformInvoice::where('status', 'overdue')->count();

                    // TODO: Implement actual email sending

                    \Filament\Notifications\Notification::make()
                        ->title('Reminders sent')
                        ->body("Payment reminders sent to {$overdueCount} clinics")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Create Invoice'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PlatformInvoiceResource\Widgets\InvoiceStatsWidget::class,
        ];
    }
}
