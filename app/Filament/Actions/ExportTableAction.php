<?php

namespace App\Filament\Actions;

use App\Filament\Exports\TableExport;
use Filament\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Support\Str;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportTableAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'export';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('core::core.export'));
        $this->icon('heroicon-o-arrow-down-tray');
        $this->color('gray');

        $this->action(function (Component $livewire): BinaryFileResponse {
            $table = $livewire->getTable();
            $columns = $this->getExportableColumns($table->getVisibleColumns());
            $query = $livewire->getFilteredTableQuery();

            $filename = $this->generateFilename($livewire);

            $export = new TableExport($query, $columns);

            return Excel::download($export, $filename);
        });
    }

    /**
     * Filter columns that can be exported (exclude images, etc.)
     */
    protected function getExportableColumns(array $columns): array
    {
        return collect($columns)
            ->filter(function (Column $column) {
                // Skip image columns
                if ($column instanceof ImageColumn) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * Generate a filename for the export
     */
    protected function generateFilename(Component $livewire): string
    {
        $resourceClass = $livewire::getResource();
        $modelLabel = $resourceClass::getPluralModelLabel();
        $date = now()->format('Y-m-d_H-i-s');

        return Str::slug($modelLabel) . '_' . $date . '.xlsx';
    }
}
