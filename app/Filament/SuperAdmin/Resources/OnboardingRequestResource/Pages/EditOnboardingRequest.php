<?php

namespace App\Filament\SuperAdmin\Resources\OnboardingRequestResource\Pages;

use App\Filament\SuperAdmin\Resources\OnboardingRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOnboardingRequest extends EditRecord
{
    protected static string $resource = OnboardingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->status === 'pending'),
        ];
    }
}
