<?php

namespace App\Filament\Traits;

use App\Filament\Actions\ImportTableAction;
use App\Filament\Imports\GenericImporter;

trait HasImportAction
{
    /**
     * Check if import is enabled for this resource.
     * Override in specific list pages to disable import.
     */
    protected function hasImportAction(): bool
    {
        return true;
    }

    /**
     * Get the model class for import.
     */
    protected function getImportModelClass(): string
    {
        return static::getResource()::getModel();
    }

    /**
     * Get the specific importer class for this resource if it exists.
     * Returns null to use GenericImporter.
     */
    protected function getSpecificImporterClass(): ?string
    {
        $modelClass = $this->getImportModelClass();
        $modelName = class_basename($modelClass);

        // Check for specific importer in App\Filament\Imports namespace
        $specificImporter = "App\\Filament\\Imports\\{$modelName}Importer";

        if (class_exists($specificImporter)) {
            return $specificImporter;
        }

        return null;
    }

    /**
     * Get the import header action.
     */
    protected function getImportHeaderAction(): ?ImportTableAction
    {
        if (!$this->hasImportAction()) {
            return null;
        }

        $modelClass = $this->getImportModelClass();
        $importerClass = $this->getSpecificImporterClass();

        if ($importerClass) {
            // Use specific importer if available
            return ImportTableAction::make()
                ->importer($importerClass)
                ->resourceClass(static::getResource());
        }

        // Use generic importer
        GenericImporter::setModel($modelClass);

        return ImportTableAction::make()
            ->importer(GenericImporter::class)
            ->resourceClass(static::getResource());
    }
}
