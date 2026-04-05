<?php

namespace Modules\Website\Models;

use XLinic\Framework\Core\Model\BaseModel;

class WebsiteMenu extends BaseModel
{
    protected $table = 'website_menus';

    protected $fillable = [
        'location',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    /**
     * Get menu by location.
     */
    public static function getByLocation(string $location): ?static
    {
        return static::where('location', $location)->first();
    }

    /**
     * Get menu items for a location.
     */
    public static function getItems(string $location): array
    {
        $menu = static::getByLocation($location);
        return $menu?->items ?? [];
    }

    /**
     * Set menu items for a location.
     */
    public static function setItems(string $location, array $items): static
    {
        return static::updateOrCreate(
            ['location' => $location],
            ['items' => $items]
        );
    }

    /**
     * Get header menu.
     */
    public static function getHeader(): array
    {
        return static::getItems('header');
    }

    /**
     * Get footer menu.
     */
    public static function getFooter(): array
    {
        return static::getItems('footer');
    }

    /**
     * Get formatted menu items with translations resolved.
     */
    public function getFormattedItems(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $items = $this->items ?? [];

        return array_map(function ($item) use ($locale) {
            return $this->formatMenuItem($item, $locale);
        }, $items);
    }

    /**
     * Format a single menu item.
     */
    protected function formatMenuItem(array $item, string $locale): array
    {
        $formatted = [
            'label' => $this->resolveTranslation($item['label'] ?? '', $locale),
            'url' => $item['url'] ?? '#',
            'target' => $item['target'] ?? '_self',
            'icon' => $item['icon'] ?? null,
        ];

        // Handle nested children
        if (!empty($item['children'])) {
            $formatted['children'] = array_map(function ($child) use ($locale) {
                return $this->formatMenuItem($child, $locale);
            }, $item['children']);
        }

        return $formatted;
    }

    /**
     * Resolve translation from a value.
     */
    protected function resolveTranslation(mixed $value, string $locale): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value[$locale] ?? $value['en'] ?? '';
        }

        return '';
    }

    /**
     * Add a menu item.
     */
    public function addItem(array $item): self
    {
        $items = $this->items ?? [];
        $items[] = $item;
        $this->items = $items;
        $this->save();

        return $this;
    }

    /**
     * Remove a menu item by index.
     */
    public function removeItem(int $index): self
    {
        $items = $this->items ?? [];
        unset($items[$index]);
        $this->items = array_values($items);
        $this->save();

        return $this;
    }

    /**
     * Reorder menu items.
     */
    public function reorderItems(array $newOrder): self
    {
        $items = $this->items ?? [];
        $reordered = [];

        foreach ($newOrder as $index) {
            if (isset($items[$index])) {
                $reordered[] = $items[$index];
            }
        }

        $this->items = $reordered;
        $this->save();

        return $this;
    }

    /**
     * Get available menu locations.
     */
    public static function getLocations(): array
    {
        return [
            'header' => __('website::website.menu_locations.header'),
            'footer' => __('website::website.menu_locations.footer'),
        ];
    }
}
