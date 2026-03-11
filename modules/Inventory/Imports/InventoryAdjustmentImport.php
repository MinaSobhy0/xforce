<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\InventoryAdjustmentLine;

class InventoryAdjustmentImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    protected InventoryAdjustment $adjustment;
    protected int $updatedCount = 0;
    protected int $skippedCount = 0;
    protected array $errors = [];

    public function __construct(InventoryAdjustment $adjustment)
    {
        $this->adjustment = $adjustment;
    }

    public function collection(Collection $rows): void
    {
        if (!$this->adjustment->isDraft()) {
            $this->errors[] = __('inventory::inventory.excel.error_not_draft');
            return;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because of 0-index and header row

                try {
                    $this->processRow($row, $rowNumber);
                } catch (\Exception $e) {
                    $this->errors[] = __('inventory::inventory.excel.error_row', [
                        'row' => $rowNumber,
                        'message' => $e->getMessage(),
                    ]);
                    $this->skippedCount++;
                }
            }

            // Recalculate totals after all updates
            $this->adjustment->recalculateTotals();
        });
    }

    protected function processRow(Collection $row, int $rowNumber): void
    {
        // Get product_id from row - handle both English and Arabic column names
        $productId = $row['product_id'] ?? $row['رقم_المنتج'] ?? null;
        $countedQty = $row['counted_qty'] ?? $row['الكمية_المحسوبة'] ?? null;
        $notes = $row['notes'] ?? $row['ملاحظات'] ?? null;

        if (empty($productId)) {
            $this->skippedCount++;
            return;
        }

        // Validate counted_qty is a number
        if ($countedQty === null || $countedQty === '') {
            $this->skippedCount++;
            return;
        }

        $countedQty = (int) $countedQty;

        // Find the adjustment line
        $line = $this->adjustment->lines()
            ->where('product_id', $productId)
            ->first();

        if (!$line) {
            $this->errors[] = __('inventory::inventory.excel.error_product_not_found', [
                'row' => $rowNumber,
                'product_id' => $productId,
            ]);
            $this->skippedCount++;
            return;
        }

        // Validate non-negative quantity
        if ($countedQty < 0) {
            $this->errors[] = __('inventory::inventory.excel.error_negative_qty', [
                'row' => $rowNumber,
            ]);
            $this->skippedCount++;
            return;
        }

        // Update the line
        $line->counted_qty = $countedQty;
        if ($notes !== null) {
            $line->notes = $notes;
        }
        $line->save();

        $this->updatedCount++;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer',
            'counted_qty' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'product_id.required' => __('inventory::inventory.excel.validation.product_id_required'),
            'counted_qty.required' => __('inventory::inventory.excel.validation.counted_qty_required'),
            'counted_qty.integer' => __('inventory::inventory.excel.validation.counted_qty_integer'),
            'counted_qty.min' => __('inventory::inventory.excel.validation.counted_qty_min'),
        ];
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
