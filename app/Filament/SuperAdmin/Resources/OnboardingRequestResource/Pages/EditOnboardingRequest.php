<?php

namespace App\Filament\SuperAdmin\Resources\OnboardingRequestResource\Pages;

use App\Filament\SuperAdmin\Resources\OnboardingRequestResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditOnboardingRequest extends BaseEditRecord
{
    protected static string $resource = OnboardingRequestResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->status === 'pending'),
        ];
    }
}
