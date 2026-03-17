<?php

namespace Modules\Evaluations\Filament\Resources\EvaluationResource\Pages;

use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Modules\Evaluations\Filament\Resources\EvaluationResource;
use Modules\Evaluations\Models\Evaluation;

class ListEvaluations extends ListRecords
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('evaluations::evaluations.tabs.all'))
                ->badge(Evaluation::count())
                ->badgeColor('gray'),

            'positive' => Tab::make(__('evaluations::evaluations.tabs.positive'))
                ->modifyQueryUsing(fn (Builder $query) => $query->positive())
                ->badge(Evaluation::positive()->count())
                ->badgeColor('success')
                ->icon('heroicon-o-face-smile'),

            'neutral' => Tab::make(__('evaluations::evaluations.tabs.neutral'))
                ->modifyQueryUsing(fn (Builder $query) => $query->neutral())
                ->badge(Evaluation::neutral()->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-minus-circle'),

            'negative' => Tab::make(__('evaluations::evaluations.tabs.negative'))
                ->modifyQueryUsing(fn (Builder $query) => $query->negative())
                ->badge(Evaluation::negative()->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-face-frown'),

            'with_feedback' => Tab::make(__('evaluations::evaluations.tabs.with_feedback'))
                ->modifyQueryUsing(fn (Builder $query) => $query->withFeedback())
                ->badge(Evaluation::withFeedback()->count())
                ->badgeColor('info')
                ->icon('heroicon-o-chat-bubble-left-ellipsis'),
        ];
    }
}
