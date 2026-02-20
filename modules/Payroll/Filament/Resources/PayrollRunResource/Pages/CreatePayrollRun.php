<?php

namespace Modules\Payroll\Filament\Resources\PayrollRunResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Payroll\Filament\Resources\PayrollRunResource;
use Modules\Payroll\Models\PayrollLine;
use Modules\Staff\Models\StaffProfile;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function afterCreate(): void
    {
        // Generate payroll lines for all active staff
        $staffProfiles = StaffProfile::active()->get();

        foreach ($staffProfiles as $staff) {
            $line = PayrollLine::generateFromStaffProfile($staff, $this->record);
            $line->save();
        }

        // Recalculate totals
        $this->record->recalculateTotals();
    }
}
