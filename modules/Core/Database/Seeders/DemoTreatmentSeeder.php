<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Treatments\Models\TreatmentCategory;
use Modules\Treatments\Models\Treatment;

class DemoTreatmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Categories
        $laserCategory = TreatmentCategory::firstOrCreate(
            ['slug' => 'laser-treatments'],
            [
                'name' => ['en' => 'Laser Treatments', 'ar' => 'علاجات الليزر'],
                'description' => ['en' => 'Professional laser hair removal and skin treatments', 'ar' => 'إزالة الشعر بالليزر وعلاجات البشرة الاحترافية'],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $skinCategory = TreatmentCategory::firstOrCreate(
            ['slug' => 'skin-care'],
            [
                'name' => ['en' => 'Skin Care', 'ar' => 'العناية بالبشرة'],
                'description' => ['en' => 'Facial treatments and skin rejuvenation', 'ar' => 'علاجات الوجه وتجديد البشرة'],
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Treatments
        $treatments = [
            [
                'code' => 'TRT-001',
                'category_id' => $laserCategory->id,
                'name' => ['en' => 'Full Body Laser', 'ar' => 'ليزر الجسم الكامل'],
                'description' => ['en' => 'Complete body hair removal treatment', 'ar' => 'علاج إزالة شعر الجسم الكامل'],
                'duration_minutes' => 90,
                'price_minor' => 350000, // 3500 EGP
                'is_active' => true,
            ],
            [
                'code' => 'TRT-002',
                'category_id' => $laserCategory->id,
                'name' => ['en' => 'Face Laser', 'ar' => 'ليزر الوجه'],
                'description' => ['en' => 'Facial hair removal treatment', 'ar' => 'علاج إزالة شعر الوجه'],
                'duration_minutes' => 30,
                'price_minor' => 80000, // 800 EGP
                'is_active' => true,
            ],
            [
                'code' => 'TRT-003',
                'category_id' => $laserCategory->id,
                'name' => ['en' => 'Underarm Laser', 'ar' => 'ليزر تحت الإبط'],
                'description' => ['en' => 'Underarm hair removal', 'ar' => 'إزالة شعر تحت الإبط'],
                'duration_minutes' => 15,
                'price_minor' => 40000, // 400 EGP
                'is_active' => true,
            ],
            [
                'code' => 'TRT-004',
                'category_id' => $skinCategory->id,
                'name' => ['en' => 'Hydrafacial', 'ar' => 'هيدرافيشل'],
                'description' => ['en' => 'Deep cleansing and hydration treatment', 'ar' => 'تنظيف عميق وترطيب للبشرة'],
                'duration_minutes' => 45,
                'price_minor' => 150000, // 1500 EGP
                'is_active' => true,
            ],
            [
                'code' => 'TRT-005',
                'category_id' => $skinCategory->id,
                'name' => ['en' => 'Chemical Peel', 'ar' => 'تقشير كيميائي'],
                'description' => ['en' => 'Skin resurfacing treatment', 'ar' => 'علاج تجديد سطح البشرة'],
                'duration_minutes' => 30,
                'price_minor' => 100000, // 1000 EGP
                'is_active' => true,
            ],
        ];

        foreach ($treatments as $treatmentData) {
            Treatment::firstOrCreate(
                ['code' => $treatmentData['code']],
                $treatmentData
            );
        }

        $this->command->info('Treatment categories and treatments seeded.');
    }
}
