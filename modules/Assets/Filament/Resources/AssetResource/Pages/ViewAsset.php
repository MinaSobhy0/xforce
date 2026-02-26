<?php

namespace Modules\Assets\Filament\Resources\AssetResource\Pages;

use Modules\Assets\Filament\Resources\AssetResource;
use Modules\Assets\Models\Asset;
use Modules\Assets\Services\AssetService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),

            Actions\Action::make('activate')
                ->label(__('assets::assets.actions.activate'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('assets::assets.actions.activate_description'))
                ->visible(fn () => $this->record->canActivate())
                ->action(function () {
                    $service = app(AssetService::class);
                    if ($service->activateAsset($this->record)) {
                        Notification::make()
                            ->title(__('assets::assets.messages.activated'))
                            ->success()
                            ->send();

                        $this->refreshFormData(['status', 'acquisition_journal_entry_id']);
                    } else {
                        Notification::make()
                            ->title(__('assets::assets.messages.cannot_activate'))
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('dispose')
                ->label(__('assets::assets.actions.dispose'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => $this->record->canDispose())
                ->form([
                    \Filament\Forms\Components\Select::make('disposal_method')
                        ->label(__('assets::assets.asset.fields.disposal_method'))
                        ->options(Asset::DISPOSAL_METHODS)
                        ->required(),

                    \Filament\Forms\Components\TextInput::make('disposal_value')
                        ->label(__('assets::assets.asset.fields.disposal_value'))
                        ->numeric()
                        ->default(0)
                        ->suffix(current_currency()),

                    \Filament\Forms\Components\DatePicker::make('disposal_date')
                        ->label(__('assets::assets.asset.fields.disposal_date'))
                        ->default(now())
                        ->required(),

                    \Filament\Forms\Components\Textarea::make('disposal_notes')
                        ->label(__('assets::assets.asset.fields.disposal_notes'))
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $service = app(AssetService::class);
                    $service->disposeAsset(
                        $this->record,
                        $data['disposal_method'],
                        (int) ($data['disposal_value'] * 100),
                        $data['disposal_notes'],
                        $data['disposal_date'] ? \Carbon\Carbon::parse($data['disposal_date']) : null
                    );

                    Notification::make()
                        ->title(__('assets::assets.messages.disposed'))
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'disposal_date',
                        'disposal_method',
                        'disposal_value_minor',
                        'disposal_notes',
                    ]);
                }),

            Actions\Action::make('view_schedule')
                ->label(__('assets::assets.actions.view_schedule'))
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->modalHeading(__('assets::assets.actions.view_schedule'))
                ->modalContent(fn () => view('assets::filament.depreciation-schedule', [
                    'schedule' => $this->record->getDepreciationSchedule(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->visible(fn () => $this->record->assetType?->hasDepreciation()),
        ];
    }
}
