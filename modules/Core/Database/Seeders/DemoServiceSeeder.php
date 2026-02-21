<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\Service;

class DemoServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Categories
        $laserCategory = ServiceCategory::firstOrCreate(
            ['slug' => 'laser-services'],
            [
                'name' => ['en' => 'Laser Services', 'ar' => 'خدمات الليزر'],
                'description' => ['en' => 'Professional laser hair removal and skin services', 'ar' => 'إزالة الشعر بالليزر وخدمات البشرة الاحترافية'],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $skinCategory = ServiceCategory::firstOrCreate(
            ['slug' => 'skin-care'],
            [
                'name' => ['en' => 'Skin Care', 'ar' => 'العناية بالبشرة'],
                'description' => ['en' => 'Facial services and skin rejuvenation', 'ar' => 'خدمات الوجه وتجديد البشرة'],
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Services
        $services = [
            [
                'code' => 'SVC-001',
                'category_id' => $laserCategory->id,
                'name' => ['en' => 'Full Body Laser', 'ar' => 'ليزر الجسم الكامل'],
                'description' => ['en' => 'Complete body hair removal service', 'ar' => 'خدمة إزالة شعر الجسم الكامل'],
                'duration_minutes' => 90,
                'price_minor' => 350000, // 3500 EGP
                'is_active' => true,
            ],
            [
                'code' => 'SVC-002',
                'category_id' => $laserCategory->id,
                'name' => ['en' => 'Face Laser', 'ar' => 'ليزر الوجه'],
                'description' => ['en' => 'Facial hair removal service', 'ar' => 'خدمة إزالة شعر الوجه'],
                'duration_minutes' => 30,
                'price_minor' => 80000, // 800 EGP
                'is_active' => true,
            ],
            [
                'code' => 'SVC-003',
                'category_id' => $laserCategory->id,
                'name' => ['en' => 'Underarm Laser', 'ar' => 'ليزر تحت الإبط'],
                'description' => ['en' => 'Underarm hair removal', 'ar' => 'إزالة شعر تحت الإبط'],
                'duration_minutes' => 15,
                'price_minor' => 40000, // 400 EGP
                'is_active' => true,
            ],
            [
                'code' => 'SVC-004',
                'category_id' => $skinCategory->id,
                'name' => ['en' => 'Hydrafacial', 'ar' => 'هيدرافيشل'],
                'description' => ['en' => 'Deep cleansing and hydration service', 'ar' => 'تنظيف عميق وترطيب للبشرة'],
                'duration_minutes' => 45,
                'price_minor' => 150000, // 1500 EGP
                'is_active' => true,
            ],
            [
                'code' => 'SVC-005',
                'category_id' => $skinCategory->id,
                'name' => ['en' => 'Chemical Peel', 'ar' => 'تقشير كيميائي'],
                'description' => ['en' => 'Skin resurfacing service', 'ar' => 'خدمة تجديد سطح البشرة'],
                'duration_minutes' => 30,
                'price_minor' => 100000, // 1000 EGP
                'is_active' => true,
            ],
        ];

        foreach ($services as $serviceData) {
            Service::firstOrCreate(
                ['code' => $serviceData['code']],
                $serviceData
            );
        }

        $this->command->info('Service categories and services seeded.');
    }
}
