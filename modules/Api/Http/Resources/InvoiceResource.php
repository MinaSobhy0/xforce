<?php

namespace Modules\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'total' => $this->total_minor / 100,
            'paid_amount' => $this->paid_amount_minor / 100,
            'balance' => ($this->total_minor - $this->paid_amount_minor) / 100,
            'currency' => 'EGP',
            'due_date' => $this->due_date?->format('Y-m-d'),
            'treatment' => $this->appointment?->treatment ? [
                'id' => $this->appointment->treatment->id,
                'name' => $this->getTranslatedName($this->appointment->treatment->name),
            ] : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    protected function getTranslatedName($value): ?string
    {
        if (!$value) return null;

        if (is_array($decoded = json_decode($value, true))) {
            return $decoded[app()->getLocale()] ?? $decoded['en'] ?? $value;
        }
        return $value;
    }
}
