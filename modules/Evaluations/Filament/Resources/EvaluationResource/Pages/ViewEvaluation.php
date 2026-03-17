<?php

namespace Modules\Evaluations\Filament\Resources\EvaluationResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Evaluations\Filament\Resources\EvaluationResource;

class ViewEvaluation extends ViewRecord
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
