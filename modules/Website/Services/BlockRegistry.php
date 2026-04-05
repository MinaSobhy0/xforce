<?php

namespace Modules\Website\Services;

class BlockRegistry
{
    /**
     * Get all available block types.
     */
    public function getBlockTypes(): array
    {
        return config('website.block_types', $this->getDefaultBlockTypes());
    }

    /**
     * Get a specific block type configuration.
     */
    public function getBlockType(string $type): ?array
    {
        return $this->getBlockTypes()[$type] ?? null;
    }

    /**
     * Check if a block type exists.
     */
    public function hasBlockType(string $type): bool
    {
        return isset($this->getBlockTypes()[$type]);
    }

    /**
     * Get block types for Filament forms.
     */
    public function getBlockTypesForForm(): array
    {
        $types = [];

        foreach ($this->getBlockTypes() as $key => $config) {
            $types[$key] = $config['name'] ?? ucfirst($key);
        }

        return $types;
    }

    /**
     * Get the default block types.
     */
    protected function getDefaultBlockTypes(): array
    {
        return [
            'hero' => [
                'name' => 'Hero',
                'icon' => 'heroicon-o-rectangle-group',
                'description' => 'Full-width hero with title, subtitle, and CTA buttons',
            ],
            'features' => [
                'name' => 'Features',
                'icon' => 'heroicon-o-squares-2x2',
                'description' => 'Grid of icon + title + description cards',
            ],
            'services' => [
                'name' => 'Services',
                'icon' => 'heroicon-o-sparkles',
                'description' => 'Display clinic services from Services module',
            ],
            'team' => [
                'name' => 'Team',
                'icon' => 'heroicon-o-user-group',
                'description' => 'Staff profiles from Staff module',
            ],
            'testimonials' => [
                'name' => 'Testimonials',
                'icon' => 'heroicon-o-chat-bubble-bottom-center-text',
                'description' => 'Customer reviews carousel/grid',
            ],
            'contact' => [
                'name' => 'Contact',
                'icon' => 'heroicon-o-envelope',
                'description' => 'Contact form + map + info',
            ],
            'gallery' => [
                'name' => 'Gallery',
                'icon' => 'heroicon-o-photo',
                'description' => 'Image gallery with lightbox',
            ],
            'cta' => [
                'name' => 'Call to Action',
                'icon' => 'heroicon-o-megaphone',
                'description' => 'Call-to-action banner',
            ],
            'text' => [
                'name' => 'Text Content',
                'icon' => 'heroicon-o-document-text',
                'description' => 'Rich text content block',
            ],
            'before-after' => [
                'name' => 'Before/After',
                'icon' => 'heroicon-o-arrows-right-left',
                'description' => 'Treatment results slider',
            ],
        ];
    }

    /**
     * Get the default content schema for a block type.
     */
    public function getDefaultContent(string $type): array
    {
        return match ($type) {
            'hero' => [
                'eyebrow' => ['en' => '', 'ar' => ''],
                'title' => ['en' => '', 'ar' => ''],
                'subtitle' => ['en' => '', 'ar' => ''],
                'primary_button' => [
                    'label' => ['en' => 'Book Now', 'ar' => 'احجز الآن'],
                    'url' => '/book',
                ],
                'secondary_button' => [
                    'label' => ['en' => 'Learn More', 'ar' => 'اعرف المزيد'],
                    'url' => '#services',
                ],
                'background_image' => null,
            ],
            'features' => [
                'title' => ['en' => '', 'ar' => ''],
                'subtitle' => ['en' => '', 'ar' => ''],
                'items' => [],
            ],
            'services' => [
                'title' => ['en' => 'Our Services', 'ar' => 'خدماتنا'],
                'subtitle' => ['en' => '', 'ar' => ''],
                'show_prices' => false,
                'show_booking' => true,
                'category_ids' => [],
                'limit' => 6,
            ],
            'team' => [
                'title' => ['en' => 'Meet Our Team', 'ar' => 'تعرف على فريقنا'],
                'subtitle' => ['en' => '', 'ar' => ''],
                'show_bio' => true,
                'staff_ids' => [],
            ],
            'testimonials' => [
                'title' => ['en' => 'What Our Clients Say', 'ar' => 'ماذا يقول عملاؤنا'],
                'items' => [],
            ],
            'contact' => [
                'title' => ['en' => 'Contact Us', 'ar' => 'اتصل بنا'],
                'show_form' => true,
                'show_map' => true,
                'show_info' => true,
            ],
            'gallery' => [
                'title' => ['en' => '', 'ar' => ''],
                'images' => [],
                'columns' => 3,
            ],
            'cta' => [
                'title' => ['en' => '', 'ar' => ''],
                'subtitle' => ['en' => '', 'ar' => ''],
                'button_label' => ['en' => 'Get Started', 'ar' => 'ابدأ الآن'],
                'button_url' => '/book',
            ],
            'text' => [
                'content' => ['en' => '', 'ar' => ''],
            ],
            'before-after' => [
                'title' => ['en' => 'Results', 'ar' => 'النتائج'],
                'items' => [],
            ],
            default => [],
        };
    }

    /**
     * Get the default settings for a block type.
     */
    public function getDefaultSettings(string $type): array
    {
        return match ($type) {
            'hero' => [
                'height' => 'full',
                'text_alignment' => 'center',
                'overlay_opacity' => 0.5,
                'overlay_color' => '#000000',
            ],
            'features' => [
                'columns' => 3,
                'icon_style' => 'outline',
            ],
            'services' => [
                'layout' => 'grid',
                'columns' => 3,
            ],
            'team' => [
                'layout' => 'grid',
                'columns' => 4,
            ],
            'testimonials' => [
                'layout' => 'carousel',
                'autoplay' => true,
            ],
            'contact' => [
                'layout' => 'split',
            ],
            'gallery' => [
                'layout' => 'masonry',
                'lightbox' => true,
            ],
            'cta' => [
                'style' => 'gradient',
                'background_color' => null,
            ],
            'text' => [
                'max_width' => 'prose',
            ],
            'before-after' => [
                'layout' => 'slider',
            ],
            default => [],
        };
    }
}
