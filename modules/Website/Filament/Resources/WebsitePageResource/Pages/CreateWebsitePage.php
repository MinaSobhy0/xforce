<?php

namespace Modules\Website\Filament\Resources\WebsitePageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Website\Filament\Resources\WebsitePageResource;
use Modules\Website\Models\WebsiteBlock;

class CreateWebsitePage extends CreateRecord
{
    protected static string $resource = WebsitePageResource::class;

    protected function afterCreate(): void
    {
        $this->saveBlocks();
    }

    protected function saveBlocks(): void
    {
        $blocksData = $this->data['blocks_data'] ?? [];

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
