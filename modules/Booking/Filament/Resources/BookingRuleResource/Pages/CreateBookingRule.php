<?php

namespace Modules\Booking\Filament\Resources\BookingRuleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Booking\Filament\Resources\BookingRuleResource;

class CreateBookingRule extends CreateRecord
{
    protected static string $resource = BookingRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        // Clean up empty condition arrays
        if (isset($data['conditions'])) {
            $data['conditions'] = array_filter($data['conditions'], fn ($v) => !empty($v));
        }

        // Clean up empty action arrays
        if (isset($data['actions'])) {
            $data['actions'] = array_filter($data['actions'], fn ($v) => $v !== null && $v !== '');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
