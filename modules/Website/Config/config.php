<?php

return [
    'name' => 'Website',

    /*
    |--------------------------------------------------------------------------
    | Block Types
    |--------------------------------------------------------------------------
    |
    | Available block types for the website builder.
    |
    */
    'block_types' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Subdomains
    |--------------------------------------------------------------------------
    |
    | Subdomains that should not trigger tenant website rendering.
    |
    */
    'excluded_subdomains' => [
        'www',
        'sys',
        'api',
        'admin',
        'platform',
        'mail',
        'smtp',
        'ftp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Theme Colors
    |--------------------------------------------------------------------------
    */
    'default_colors' => [
        'primary' => '#3B82F6',
        'secondary' => '#10B981',
        'accent' => '#F59E0B',
    ],
];
