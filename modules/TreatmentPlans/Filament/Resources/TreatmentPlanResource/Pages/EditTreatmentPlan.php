<?php

namespace Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\Pages;

use Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditTreatmentPlan extends BaseEditRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        // Only allow editing if plan is in an editable state
        if (!$this->record->isEditable()) {
            abort(403, __('treatment_plans::treatment_plans.messages.cannot_edit'));
        }
    }
}
