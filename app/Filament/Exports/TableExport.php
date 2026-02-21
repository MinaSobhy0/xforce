<?php

namespace App\Filament\Exports;

use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Str;

class TableExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    use Exportable;

    protected Builder $query;
    protected array $columns;

    public function __construct(Builder $query, array $columns)
    {
        $this->query = $query;
        $this->columns = $columns;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return collect($this->columns)
            ->map(fn (Column $column) => $column->getLabel() ?? Str::headline($column->getName()))
            ->all();
    }

    public function map($record): array
    {
        $row = [];

        foreach ($this->columns as $column) {
            $row[] = $this->getColumnValue($column, $record);
        }

        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the first row as bold (headers)
            1 => ['font' => ['bold' => true]],
        ];
    }

    protected function getColumnValue(Column $column, Model $record): mixed
    {
        // Get the column's state for this record
        $column->record($record);

        $name = $column->getName();
        $state = $column->getState();

        // Handle relationships (e.g., patient.name)
        if (str_contains($name, '.')) {
            $state = data_get($record, $name);
        }

        // Handle boolean columns
        if ($column instanceof BooleanColumn || $column instanceof IconColumn) {
            if ($column instanceof IconColumn && $column->isBoolean()) {
                return $state ? __('core::core.yes') : __('core::core.no');
            }
            if ($column instanceof BooleanColumn) {
                return $state ? __('core::core.yes') : __('core::core.no');
            }
        }

        // Handle arrays/collections
        if (is_array($state)) {
            return implode(', ', $state);
        }

        if ($state instanceof \Illuminate\Support\Collection) {
            return $state->implode(', ');
        }

        // Handle dates - return as DateTime for Excel formatting
        if ($state instanceof \DateTimeInterface) {
            return $state;
        }

        // Handle objects with __toString
        if (is_object($state) && method_exists($state, '__toString')) {
            return (string) $state;
        }

        // Handle enums
        if ($state instanceof \BackedEnum) {
            return $state->value;
        }

        if ($state instanceof \UnitEnum) {
            return $state->name;
        }

        // Handle null
        if ($state === null) {
            return '';
        }

        // Try to get formatted state from TextColumn
        if ($column instanceof TextColumn) {
            $formattedState = $column->formatState($state);
            if (is_string($formattedState)) {
                // Strip HTML tags in case formatting includes HTML
                return strip_tags($formattedState);
            }
        }

        return $state;
    }
}
