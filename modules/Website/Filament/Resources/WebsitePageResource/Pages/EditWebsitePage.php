<?php

namespace Modules\Website\Filament\Resources\WebsitePageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Website\Filament\Resources\WebsitePageResource;
use Modules\Website\Models\WebsiteBlock;

class EditWebsitePage extends EditRecord
{
    protected static string $resource = WebsitePageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label(__('website::website.actions.preview'))
                ->icon('heroicon-o-eye')
                ->url(fn () => $this->record->url, true),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing blocks into the blocks_data field
        $blocks = $this->record->blocks()->orderBy('sort_order')->get();

        $data['blocks_data'] = $blocks->map(function ($block) {
            return [
                'type' => $block->type,
                'data' => array_merge(
                    ['content' => $block->content ?? []],
                    ['settings' => $block->settings ?? []],
                    ['is_visible' => $block->is_visible]
                ),
            ];
        })->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        $this->saveBlocks();
    }

    protected function saveBlocks(): void
    {
        $blocksData = $this->data['blocks_data'] ?? [];

        // Delete existing blocks
        $this->record->blocks()->delete();

        // Create new blocks
        foreach ($blocksData as $index => $blockData) {
            WebsiteBlock::create([
                'page_id' => $this->record->id,
                'type' => $blockData['type'],
                'content' => $blockData['data']['content'] ?? [],
                'settings' => $blockData['data']['settings'] ?? [],
                'is_visible' => $blockData['data']['is_visible'] ?? true,
                'sort_order' => $index,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
