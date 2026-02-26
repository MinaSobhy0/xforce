<?php

namespace App\Filament\Traits;

use App\Filament\Actions\ImportTableAction;
use App\Filament\Imports\BaseImporter;
use App\Filament\Imports\PatientImporter;
use App\Filament\Imports\ProductImporter;
use App\Filament\Imports\ServiceImporter;
use App\Filament\Imports\SupplierImporter;
use Filament\Actions\ImportAction;

trait HasImportAction
{
    /**
     * Get the importer class for this resource.
     */
    protected function getImporterClass(): ?string
    {
        $resource = static::getResource();
        $model = $resource::getModel();

        // Map models to their importers
        $importerMap = [
            \Modules\Inventory\Models\Product::class => ProductImporter::class,
            \Modules\Patients\Models\Patient::class => PatientImporter::class,
            \Modules\Inventory\Models\Supplier::class => SupplierImporter::class,
            \Modules\Services\Models\Service::class => ServiceImporter::class,
        ];

        return $importerMap[$model] ?? null;
    }

    /**
     * Check if import is enabled for this resource.
     */
    protected function hasImportAction(): bool
    {
        return $this->getImporterClass() !== null;
    }

    /**
     * Get the import header action.
     */
    protected function getImportHeaderAction(): ?ImportTableAction
    {
        $importerClass = $this->getImporterClass();

        if (!$importerClass) {
            return null;
        }

        return ImportTableAction::make()
            ->importer($importerClass)
            ->resourceClass(static::getResource());
    }

    /**
     * Override getHeaderActions to add import action.
     */
    protected function getHeaderActionsWithImport(): array
    {
        $actions = [];

        // Add import action if available
        $importAction = $this->getImportHeaderAction();
        if ($importAction) {
            $actions[] = $importAction;
        }

        return $actions;
    }
}
