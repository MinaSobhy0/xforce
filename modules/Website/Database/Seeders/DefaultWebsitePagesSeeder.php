<?php

namespace Modules\Website\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Website\Models\WebsiteMenu;
use Modules\Website\Models\WebsitePage;
use Modules\Website\Models\WebsiteBlock;
use Modules\Website\Models\WebsiteSetting;

class DefaultWebsitePagesSeeder extends Seeder
{
    public function run(): void
    {
        // Create default website settings
        $this->seedSettings();

        // Create default menus
        $this->seedMenus();

        // Create default homepage
        $this->seedHomepage();
    }

    protected function seedSettings(): void
    {
        $defaults = [
            'primary_color' => '#3B82F6',
            'secondary_color' => '#10B981',
            'accent_color' => '#F59E0B',
        ];

        foreach ($defaults as $key => $value) {
            WebsiteSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    protected function seedMenus(): void
    {
        // Header menu
        WebsiteMenu::firstOrCreate(
            ['location' => 'header'],
            [
                'items' => [
                    [
                        'label' => ['en' => 'Home', 'ar' => 'الرئيسية'],
                        'url' => '/',
                        'target' => '_self',
                    ],
                    [
                        'label' => ['en' => 'Services', 'ar' => 'الخدمات'],
                        'url' => '#services',
                        'target' => '_self',
                    ],
                    [
                        'label' => ['en' => 'About', 'ar' => 'من نحن'],
                        'url' => '#about',
                        'target' => '_self',
                    ],
                    [
                        'label' => ['en' => 'Contact', 'ar' => 'اتصل بنا'],
                        'url' => '#contact',
                        'target' => '_self',
                    ],
                    [
                        'label' => ['en' => 'Book Now', 'ar' => 'احجز الآن'],
                        'url' => '/book',
                        'target' => '_self',
                    ],
                ],
            ]
        );

        // Footer menu
        WebsiteMenu::firstOrCreate(
            ['location' => 'footer'],
            [
                'items' => [
                    [
                        'label' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                        'url' => '/privacy',
                        'target' => '_self',
                    ],
                    [
                        'label' => ['en' => 'Terms of Service', 'ar' => 'شروط الخدمة'],
                        'url' => '/terms',
                        'target' => '_self',
                    ],
                ],
            ]
        );
    }

    protected function seedHomepage(): void
    {
        // Check if homepage already exists
        if (WebsitePage::where('is_homepage', true)->exists()) {
            return;
        }

        // Create homepage
        $homepage = WebsitePage::create([
            'slug' => 'home',
            'title' => [
                'en' => 'Welcome',
                'ar' => 'مرحباً',
            ],
            'meta_title' => [
                'en' => 'Welcome to Our Clinic',
                'ar' => 'مرحباً بكم في عيادتنا',
            ],
            'meta_description' => [
                'en' => 'Professional beauty and laser treatments. Book your appointment today.',
                'ar' => 'علاجات تجميل وليزر احترافية. احجز موعدك اليوم.',
            ],
            'is_homepage' => true,
            'is_published' => false, // Start unpublished so tenant can customize first
            'sort_order' => 0,
        ]);

        // Add default blocks
        $blocks = [
            [
                'type' => 'hero',
                'content' => [
                    'eyebrow' => ['en' => 'Welcome to', 'ar' => 'مرحباً بكم في'],
                    'title' => ['en' => 'Your Beauty Destination', 'ar' => 'وجهتك للجمال'],
                    'subtitle' => [
                        'en' => 'Experience world-class treatments with our expert team. Transform your look, boost your confidence.',
                        'ar' => 'اختبر علاجات عالمية المستوى مع فريقنا المتخصص. غيّر مظهرك، عزز ثقتك.',
                    ],
                    'primary_button' => [
                        'label' => ['en' => 'Book Appointment', 'ar' => 'احجز موعد'],
                        'url' => '/book',
                    ],
                    'secondary_button' => [
                        'label' => ['en' => 'Our Services', 'ar' => 'خدماتنا'],
                        'url' => '#services',
                    ],
                ],
                'settings' => [
                    'height' => 'full',
                    'text_alignment' => 'center',
                    'overlay_opacity' => 0.5,
                ],
                'sort_order' => 0,
            ],
            [
                'type' => 'services',
                'content' => [
                    'title' => ['en' => 'Our Services', 'ar' => 'خدماتنا'],
                    'subtitle' => [
                        'en' => 'Discover our range of professional beauty and wellness treatments',
                        'ar' => 'اكتشف مجموعة علاجات التجميل والعافية المتخصصة لدينا',
                    ],
                    'show_prices' => false,
                    'show_booking' => true,
                    'limit' => 6,
                ],
                'settings' => [
                    'layout' => 'grid',
                    'columns' => 3,
                ],
                'sort_order' => 1,
            ],
            [
                'type' => 'cta',
                'content' => [
                    'title' => ['en' => 'Ready to Transform?', 'ar' => 'مستعد للتغيير؟'],
                    'subtitle' => [
                        'en' => 'Book your consultation today and take the first step towards your beauty goals.',
                        'ar' => 'احجز استشارتك اليوم واتخذ الخطوة الأولى نحو أهدافك الجمالية.',
                    ],
                    'button_label' => ['en' => 'Book Now', 'ar' => 'احجز الآن'],
                    'button_url' => '/book',
                ],
                'settings' => [
                    'style' => 'gradient',
                ],
                'sort_order' => 2,
            ],
            [
                'type' => 'contact',
                'content' => [
                    'title' => ['en' => 'Get in Touch', 'ar' => 'تواصل معنا'],
                    'show_form' => true,
                    'show_map' => true,
                    'show_info' => true,
                ],
                'settings' => [
                    'layout' => 'split',
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($blocks as $blockData) {
            WebsiteBlock::create([
                'page_id' => $homepage->id,
                'type' => $blockData['type'],
                'content' => $blockData['content'],
                'settings' => $blockData['settings'],
                'sort_order' => $blockData['sort_order'],
                'is_visible' => true,
            ]);
        }
    }
}
