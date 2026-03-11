<?php

namespace Modules\Inventory\Filament\Resources\InventoryAdjustmentResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\InventoryAdjustmentTemplateExport;
use Modules\Inventory\Filament\Resources\InventoryAdjustmentResource;
use Modules\Inventory\Imports\InventoryAdjustmentImport;

class EditInventoryAdjustment extends BaseEditRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Auto-load products if this is a count type and no lines exist
        if ($this->record->isDraft() && $this->record->lines()->count() === 0) {
            $this->record->loadProductsFromStock();
            $this->record->refresh();
        }

        return $data;
    }

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\ActionGroup::make([
                Actions\Action::make('download_template')
                    ->label(__('inventory::inventory.actions.download_template'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn () => $this->record->isDraft() && $this->record->lines()->count() > 0)
                    ->action(function () {
                        $filename = "inventory_count_{$this->record->reference}_" . now()->format('Y-m-d_His') . '.xlsx';

                        return Excel::download(
                            new InventoryAdjustmentTemplateExport($this->record),
                            $filename
                        );
                    }),

                Actions\Action::make('upload_counts')
                    ->label(__('inventory::inventory.actions.upload_counts'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn () => $this->record->isDraft())
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label(__('inventory::inventory.fields.excel_file'))
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->maxSize(5120) // 5MB
                            ->required()
                            ->helperText(__('inventory::inventory.helpers.excel_upload')),
                    ])
                    ->modalHeading(__('inventory::inventory.actions.upload_counts'))
                    ->modalDescription(__('inventory::inventory.messages.upload_description'))
                    ->modalSubmitActionLabel(__('inventory::inventory.actions.import'))
                    ->action(function (array $data) {
                        $relativePath = $data['file'];
                        $filePath = Storage::disk('local')->path($relativePath);

                        // Ensure file exists
                        if (!file_exists($filePath)) {
                            Notification::make()
                                ->title(__('inventory::inventory.messages.import_failed'))
                                ->body('File not found: ' . $relativePath)
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            $import = new InventoryAdjustmentImport($this->record);
                            Excel::import($import, $filePath);

                            // Clean up the uploaded file
                            Storage::disk('local')->delete($relativePath);

                            // Show results
                            $updatedCount = $import->getUpdatedCount();
                            $skippedCount = $import->getSkippedCount();
                            $errors = $import->getErrors();

                            if ($import->hasErrors()) {
                                Notification::make()
                                    ->title(__('inventory::inventory.messages.import_completed_with_errors'))
                                    ->body(implode("\n", array_slice($errors, 0, 5)))
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title(__('inventory::inventory.messages.import_success'))
                                    ->body(__('inventory::inventory.messages.import_summary', [
                                        'updated' => $updatedCount,
                                        'skipped' => $skippedCount,
                                    ]))
                                    ->success()
                                    ->send();
                            }

                            // Refresh the page to show updated data
                            $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));

                        } catch (\Exception $e) {
                            // Clean up the uploaded file
                            Storage::disk('local')->delete($relativePath);

                            Notification::make()
                                ->title(__('inventory::inventory.messages.import_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->label(__('inventory::inventory.actions.excel_actions'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->button()
                ->visible(fn () => $this->record->isDraft()),

            Actions\Action::make('load_products')
                ->label(__('inventory::inventory.actions.load_products'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $this->record->isDraft())
                ->requiresConfirmation()
                ->modalDescription(__('inventory::inventory.messages.load_products_confirmation'))
                ->action(function () {
                    $this->record->loadProductsFromStock();
                    Notification::make()
                        ->title(__('inventory::inventory.messages.products_loaded'))
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('validate')
                ->label(__('inventory::inventory.actions.validate'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('inventory::inventory.actions.validate_adjustment'))
                ->modalDescription(__('inventory::inventory.messages.validate_confirmation'))
                ->visible(fn () => $this->record->canValidate())
                ->action(function () {
                    if ($this->record->validate()) {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.adjustment_validated'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } else {
                        Notification::make()
                            ->title(__('inventory::inventory.messages.validation_failed'))
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }
}
