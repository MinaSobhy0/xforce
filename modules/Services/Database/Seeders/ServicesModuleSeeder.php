<?php

namespace Modules\Services\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Tenant;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\Service;
use Modules\Services\Models\ConsentTemplate;

class ServicesModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Services module data...');

        $tenant = Tenant::where('slug', 'system')->first();

        if (!$tenant) {
            $this->command->warn('System tenant not found. Skipping service seeds.');
            return;
        }

        $this->seedConsentTemplates($tenant);
        $this->seedCategories($tenant);
        $this->seedServices($tenant);

        $this->command->info('Services module seeded successfully.');
    }

    private function seedConsentTemplates(Tenant $tenant): void
    {
        $templates = [
            [
                'name' => ['en' => 'Laser Treatment Consent', 'ar' => 'موافقة على العلاج بالليزر'],
                'description' => ['en' => 'General consent for laser treatments', 'ar' => 'موافقة عامة لعلاجات الليزر'],
                'content' => [
                    'en' => '<h2>Laser Treatment Consent Form</h2><p>I understand that:</p><ul><li>Laser treatments carry certain risks including but not limited to burns, scarring, and pigmentation changes</li><li>Results may vary and are not guaranteed</li><li>Multiple sessions may be required</li><li>I must follow pre and post-treatment instructions</li></ul><p>I have been informed about the treatment, its risks, and alternatives.</p>',
                    'ar' => '<h2>نموذج الموافقة على العلاج بالليزر</h2><p>أفهم أن:</p><ul><li>علاجات الليزر تحمل مخاطر معينة بما في ذلك الحروق والندوب وتغيرات التصبغ</li><li>النتائج قد تختلف وغير مضمونة</li><li>قد تكون هناك حاجة لجلسات متعددة</li><li>يجب علي اتباع تعليمات ما قبل وبعد العلاج</li></ul><p>لقد تم إبلاغي بالعلاج ومخاطره وبدائله.</p>',
                ],
                'version' => '1.0',
                'valid_days' => 365,
                'requires_witness' => false,
                'requires_patient_signature' => true,
            ],
            [
                'name' => ['en' => 'Injectable Treatment Consent', 'ar' => 'موافقة على العلاج بالحقن'],
                'description' => ['en' => 'Consent for filler and botox treatments', 'ar' => 'موافقة على علاجات الفيلر والبوتوكس'],
                'content' => [
                    'en' => '<h2>Injectable Treatment Consent Form</h2><p>I understand that:</p><ul><li>Injectable treatments may cause bruising, swelling, and temporary discomfort</li><li>Rare complications include infection, vascular occlusion, and allergic reactions</li><li>Results are temporary and maintenance treatments are required</li><li>I must disclose all medications and supplements</li></ul><p>I consent to the treatment after full disclosure of risks.</p>',
                    'ar' => '<h2>نموذج الموافقة على العلاج بالحقن</h2><p>أفهم أن:</p><ul><li>قد تسبب علاجات الحقن كدمات وتورم وعدم راحة مؤقت</li><li>المضاعفات النادرة تشمل العدوى وانسداد الأوعية وردود الفعل التحسسية</li><li>النتائج مؤقتة وتتطلب علاجات صيانة</li><li>يجب أن أكشف عن جميع الأدوية والمكملات</li></ul><p>أوافق على العلاج بعد الكشف الكامل عن المخاطر.</p>',
                ],
                'version' => '1.0',
                'valid_days' => 180,
                'requires_witness' => true,
                'requires_patient_signature' => true,
            ],
        ];

        foreach ($templates as $templateData) {
            $templateData['tenant_id'] = $tenant->id;
            $templateData['is_active'] = true;

            $template = ConsentTemplate::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'version' => $templateData['version'],
                ],
                $templateData
            );

            $name = $templateData['name']['en'];
            $this->command->info("  - Consent template created: {$name}");
        }
    }

    private function seedCategories(Tenant $tenant): void
    {
        $categories = [
            [
                'name' => ['en' => 'Laser Hair Removal', 'ar' => 'إزالة الشعر بالليزر'],
                'description' => ['en' => 'Permanent hair reduction treatments', 'ar' => 'علاجات تقليل الشعر الدائم'],
                'icon' => 'heroicon-o-sparkles',
                'color' => '#EC4899',
                'sort_order' => 1,
            ],
            [
                'name' => ['en' => 'Skin Rejuvenation', 'ar' => 'تجديد البشرة'],
                'description' => ['en' => 'Anti-aging and skin improvement treatments', 'ar' => 'علاجات مكافحة الشيخوخة وتحسين البشرة'],
                'icon' => 'heroicon-o-sun',
                'color' => '#F59E0B',
                'sort_order' => 2,
            ],
            [
                'name' => ['en' => 'Injectables', 'ar' => 'الحقن التجميلية'],
                'description' => ['en' => 'Botox and dermal fillers', 'ar' => 'البوتوكس والفيلر'],
                'icon' => 'heroicon-o-beaker',
                'color' => '#8B5CF6',
                'sort_order' => 3,
            ],
            [
                'name' => ['en' => 'Body Contouring', 'ar' => 'نحت الجسم'],
                'description' => ['en' => 'Non-surgical body shaping treatments', 'ar' => 'علاجات تشكيل الجسم غير الجراحية'],
                'icon' => 'heroicon-o-user',
                'color' => '#10B981',
                'sort_order' => 4,
            ],
            [
                'name' => ['en' => 'Pigmentation', 'ar' => 'علاج التصبغات'],
                'description' => ['en' => 'Treatment for pigmentation issues', 'ar' => 'علاج مشاكل التصبغ'],
                'icon' => 'heroicon-o-eye-dropper',
                'color' => '#6366F1',
                'sort_order' => 5,
            ],
        ];

        foreach ($categories as $categoryData) {
            $categoryData['tenant_id'] = $tenant->id;
            $categoryData['is_active'] = true;

            ServiceCategory::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name->en' => $categoryData['name']['en'],
                ],
                $categoryData
            );

            $name = $categoryData['name']['en'];
            $this->command->info("  - Category created: {$name}");
        }
    }

    private function seedServices(Tenant $tenant): void
    {
        $laserCategory = ServiceCategory::where('tenant_id', $tenant->id)
            ->whereRaw("name->>'en' = ?", ['Laser Hair Removal'])
            ->first();

        $skinCategory = ServiceCategory::where('tenant_id', $tenant->id)
            ->whereRaw("name->>'en' = ?", ['Skin Rejuvenation'])
            ->first();

        $injectablesCategory = ServiceCategory::where('tenant_id', $tenant->id)
            ->whereRaw("name->>'en' = ?", ['Injectables'])
            ->first();

        $laserConsent = ConsentTemplate::where('tenant_id', $tenant->id)
            ->whereRaw("name->>'en' LIKE ?", ['%Laser%'])
            ->first();

        $injectablesConsent = ConsentTemplate::where('tenant_id', $tenant->id)
            ->whereRaw("name->>'en' LIKE ?", ['%Injectable%'])
            ->first();

        $services = [
            // Laser Hair Removal
            [
                'category_id' => $laserCategory?->id,
                'consent_template_id' => $laserConsent?->id,
                'name' => ['en' => 'Full Legs Laser', 'ar' => 'ليزر الساقين كاملة'],
                'short_description' => ['en' => 'Complete leg hair removal', 'ar' => 'إزالة شعر الساقين بالكامل'],
                'description' => ['en' => 'Comprehensive laser hair removal for both legs from thigh to ankle.', 'ar' => 'إزالة شاملة للشعر بالليزر للساقين من الفخذ إلى الكاحل.'],
                'duration_minutes' => 60,
                'buffer_minutes' => 10,
                'base_price_minor' => 150000, // 1500 EGP
                'recommended_sessions' => 6,
                'session_interval_days' => 28,
                'fitzpatrick_min' => 1,
                'fitzpatrick_max' => 5,
                'requires_consent' => true,
                'is_bookable_online' => true,
                'sort_order' => 1,
            ],
            [
                'category_id' => $laserCategory?->id,
                'consent_template_id' => $laserConsent?->id,
                'name' => ['en' => 'Underarms Laser', 'ar' => 'ليزر تحت الإبط'],
                'short_description' => ['en' => 'Underarm hair removal', 'ar' => 'إزالة شعر تحت الإبط'],
                'duration_minutes' => 15,
                'buffer_minutes' => 5,
                'base_price_minor' => 40000, // 400 EGP
                'recommended_sessions' => 6,
                'session_interval_days' => 28,
                'fitzpatrick_min' => 1,
                'fitzpatrick_max' => 5,
                'requires_consent' => true,
                'is_bookable_online' => true,
                'sort_order' => 2,
            ],
            [
                'category_id' => $laserCategory?->id,
                'consent_template_id' => $laserConsent?->id,
                'name' => ['en' => 'Brazilian Laser', 'ar' => 'ليزر البرازيلي'],
                'short_description' => ['en' => 'Bikini area hair removal', 'ar' => 'إزالة شعر منطقة البكيني'],
                'duration_minutes' => 30,
                'buffer_minutes' => 10,
                'base_price_minor' => 80000, // 800 EGP
                'recommended_sessions' => 8,
                'session_interval_days' => 28,
                'fitzpatrick_min' => 1,
                'fitzpatrick_max' => 5,
                'requires_consent' => true,
                'is_bookable_online' => true,
                'sort_order' => 3,
            ],
            // Skin Rejuvenation
            [
                'category_id' => $skinCategory?->id,
                'name' => ['en' => 'Chemical Peel - Light', 'ar' => 'تقشير كيميائي خفيف'],
                'short_description' => ['en' => 'Superficial skin renewal', 'ar' => 'تجديد سطحي للبشرة'],
                'duration_minutes' => 30,
                'buffer_minutes' => 5,
                'base_price_minor' => 60000, // 600 EGP
                'recommended_sessions' => 4,
                'session_interval_days' => 14,
                'fitzpatrick_min' => 1,
                'fitzpatrick_max' => 4,
                'requires_consent' => false,
                'is_bookable_online' => true,
                'sort_order' => 1,
            ],
            [
                'category_id' => $skinCategory?->id,
                'name' => ['en' => 'Microneedling', 'ar' => 'الميكرونيدلينج'],
                'short_description' => ['en' => 'Collagen induction therapy', 'ar' => 'علاج تحفيز الكولاجين'],
                'duration_minutes' => 45,
                'buffer_minutes' => 15,
                'base_price_minor' => 120000, // 1200 EGP
                'recommended_sessions' => 3,
                'session_interval_days' => 30,
                'fitzpatrick_min' => 1,
                'fitzpatrick_max' => 6,
                'contraindications' => ['active_infection', 'accutane', 'blood_thinners'],
                'requires_consent' => true,
                'is_bookable_online' => true,
                'sort_order' => 2,
            ],
            // Injectables
            [
                'category_id' => $injectablesCategory?->id,
                'consent_template_id' => $injectablesConsent?->id,
                'name' => ['en' => 'Botox - Forehead', 'ar' => 'بوتوكس - الجبهة'],
                'short_description' => ['en' => 'Forehead wrinkle treatment', 'ar' => 'علاج تجاعيد الجبهة'],
                'duration_minutes' => 20,
                'buffer_minutes' => 10,
                'base_price_minor' => 200000, // 2000 EGP
                'session_interval_days' => 120,
                'contraindications' => ['pregnancy', 'breastfeeding', 'autoimmune'],
                'requires_consent' => true,
                'is_bookable_online' => false,
                'sort_order' => 1,
            ],
            [
                'category_id' => $injectablesCategory?->id,
                'consent_template_id' => $injectablesConsent?->id,
                'name' => ['en' => 'Lip Filler', 'ar' => 'فيلر الشفاه'],
                'short_description' => ['en' => 'Lip augmentation', 'ar' => 'تكبير الشفاه'],
                'duration_minutes' => 30,
                'buffer_minutes' => 15,
                'base_price_minor' => 350000, // 3500 EGP
                'session_interval_days' => 180,
                'contraindications' => ['pregnancy', 'breastfeeding', 'herpes', 'autoimmune'],
                'requires_consent' => true,
                'is_bookable_online' => false,
                'sort_order' => 2,
            ],
        ];

        foreach ($services as $serviceData) {
            $serviceData['tenant_id'] = $tenant->id;
            $serviceData['is_active'] = true;

            Service::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name->en' => $serviceData['name']['en'],
                ],
                $serviceData
            );

            $name = $serviceData['name']['en'];
            $this->command->info("  - Service created: {$name}");
        }
    }
}
