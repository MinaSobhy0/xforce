<?php

namespace Modules\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'treatment' => [
                'id' => $this->treatment?->id,
                'name' => $this->getTranslatedName($this->treatment?->name),
            ],
            'branch' => [
                'id' => $this->branch?->id,
                'name' => $this->getTranslatedName($this->branch?->name),
            ],
            'practitioner' => [
                'id' => $this->practitioner?->id,
                'name' => $this->practitioner?->name,
            ],
            'booked_via' => $this->booked_via,
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
