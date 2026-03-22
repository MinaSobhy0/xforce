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

        // Check export permission based on resource's permission key
        $this->visible(function (Component $livewire): bool {
            $user = auth()->user();
            if (!$user) return false;

            // Super admin and key roles always have access
            if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
                return true;
            }

            // Get permission key from resource
            $resourceClass = $livewire::getResource();

            // Use getter method if available (from ChecksResourcePermissions trait)
            if (method_exists($resourceClass, 'getPermissionKey')) {
                $permissionKey = $resourceClass::getPermissionKey();
            } else {
                // Fallback: derive from resource name
                $permissionKey = strtolower(str_replace('Resource', '', class_basename($resourceClass)));
            }

            $permission = "{$permissionKey}.export";

            // Check permission
            if ($user->can($permission)) {
                return true;
            }

            // If permission doesn't exist, hide by default (more restrictive)
            return false;
        });

        $this->action(function (Component $livewire): BinaryFileResponse {
            $table = $livewire->getTable();
            $columns = $this->getExportableColumns($table->getVisibleColumns());
            $query = $livewire->getFilteredTableQuery();

            // SECURITY: Limit export to prevent DoS via massive data exports
            $maxExportRows = config('app.max_export_rows', 10000);
            $totalRows = $query->count();

            if ($totalRows > $maxExportRows) {
                \Filament\Notifications\Notification::make()
                    ->title(__('core::core.export_limit_exceeded'))
                    ->body(__('core::core.export_limit_exceeded_body', [
                        'max' => number_format($maxExportRows),
                        'total' => number_format($totalRows),
                    ]))
                    ->warning()
                    ->send();

                // Apply limit to prevent DoS
                $query->limit($maxExportRows);
            }

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
