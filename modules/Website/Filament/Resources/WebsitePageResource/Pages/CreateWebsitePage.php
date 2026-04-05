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

        // Use numeric index for sort_order, not UUID keys from Builder
        $sortOrder = 0;
        foreach ($blocksData as $blockData) {
            WebsiteBlock::create([
                'page_id' => $this->record->id,
                'type' => $blockData['type'],
                'content' => $blockData['data']['content'] ?? [],
                'settings' => $blockData['data']['settings'] ?? [],
                'is_visible' => $blockData['data']['is_visible'] ?? true,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
