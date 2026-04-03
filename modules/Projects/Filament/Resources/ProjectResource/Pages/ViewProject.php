<?php

namespace Modules\Projects\Filament\Resources\ProjectResource\Pages;

use Modules\Projects\Filament\Resources\ProjectResource;
use Modules\Projects\Models\Project;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kanban')
                ->label(__('projects::projects.actions.view_kanban'))
                ->icon('heroicon-o-view-columns')
                ->url(fn () => route('filament.tenant.pages.project-kanban', ['project' => $this->record->id])),

            Actions\EditAction::make(),

            Actions\Action::make('activate')
                ->label(__('projects::projects.actions.activate'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->canTransitionTo(Project::STATUS_ACTIVE))
                ->action(fn () => $this->record->transitionTo(Project::STATUS_ACTIVE)),

            Actions\Action::make('complete')
                ->label(__('projects::projects.actions.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canTransitionTo(Project::STATUS_COMPLETED))
                ->action(fn () => $this->record->transitionTo(Project::STATUS_COMPLETED)),
        ];
    }
}
