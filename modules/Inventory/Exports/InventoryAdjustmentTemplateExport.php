<?php

namespace Modules\Inventory\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Modules\Inventory\Models\InventoryAdjustment;

class InventoryAdjustmentTemplateExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected InventoryAdjustment $adjustment;

    public function __construct(InventoryAdjustment $adjustment)
    {
        $this->adjustment = $adjustment;
    }

    public function collection(): Collection
    {
        return $this->adjustment->lines()
            ->with('product')
            ->get()
            ->map(function ($line) {
                return [
                    'product_id' => $line->product_id,
                    'sku' => $line->product?->sku ?? '',
                    'product_name' => $line->product?->name ?? '',
                    'theoretical_qty' => $line->theoretical_qty,
                    'counted_qty' => $line->counted_qty,
                    'notes' => $line->notes ?? '',
                ];
            });
    }

    public function headings(): array
    {
        return [
            __('inventory::inventory.excel.product_id'),
            __('inventory::inventory.excel.sku'),
            __('inventory::inventory.excel.product_name'),
            __('inventory::inventory.excel.theoretical_qty'),
            __('inventory::inventory.excel.counted_qty'),
            __('inventory::inventory.excel.notes'),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Product ID
            'B' => 15,  // SKU
            'C' => 40,  // Product Name
            'D' => 15,  // Theoretical Qty
            'E' => 15,  // Counted Qty
            'F' => 30,  // Notes
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Header styling
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Data rows styling
        if ($lastRow > 1) {
            // Read-only columns (A-D) - light gray background
            $sheet->getStyle("A2:D{$lastRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'],
                ],
            ]);

            // Editable column (E - Counted Qty) - light green background
            $sheet->getStyle("E2:E{$lastRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DCFCE7'],
                ],
                'font' => [
                    'bold' => true,
                ],
            ]);

            // Notes column (F) - light blue background
            $sheet->getStyle("F2:F{$lastRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DBEAFE'],
                ],
            ]);

            // Add borders
            $sheet->getStyle("A1:F{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ]);

            // Number format for quantity columns
            $sheet->getStyle("D2:E{$lastRow}")->getNumberFormat()
                ->setFormatCode('#,##0');
        }

        // Freeze header row
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return $this->adjustment->reference ?? 'Inventory Count';
    }
}
