<?php

namespace Modules\KnowledgeBase\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\KnowledgeBase\Models\HelpCategory;

class DefaultCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => ['en' => 'Getting Started', 'ar' => 'البدء'],
                'description' => ['en' => 'Learn the basics of using the system', 'ar' => 'تعلم أساسيات استخدام النظام'],
                'slug' => 'getting-started',
                'icon' => 'heroicon-o-rocket-launch',
                'sort_order' => 1,
            ],
            [
                'name' => ['en' => 'Patient Management', 'ar' => 'إدارة المرضى'],
                'description' => ['en' => 'Managing patient records and profiles', 'ar' => 'إدارة سجلات وملفات المرضى'],
                'slug' => 'patient-management',
                'icon' => 'heroicon-o-users',
                'sort_order' => 2,
            ],
            [
                'name' => ['en' => 'Appointments', 'ar' => 'المواعيد'],
                'description' => ['en' => 'Scheduling and managing appointments', 'ar' => 'جدولة وإدارة المواعيد'],
                'slug' => 'appointments',
                'icon' => 'heroicon-o-calendar',
                'sort_order' => 3,
            ],
            [
                'name' => ['en' => 'Billing & Invoices', 'ar' => 'الفوترة والفواتير'],
                'description' => ['en' => 'Creating and managing invoices', 'ar' => 'إنشاء وإدارة الفواتير'],
                'slug' => 'billing-invoices',
                'icon' => 'heroicon-o-banknotes',
                'sort_order' => 4,
            ],
            [
                'name' => ['en' => 'Services & Treatments', 'ar' => 'الخدمات والعلاجات'],
                'description' => ['en' => 'Setting up and managing services', 'ar' => 'إعداد وإدارة الخدمات'],
                'slug' => 'services-treatments',
                'icon' => 'heroicon-o-sparkles',
                'sort_order' => 5,
            ],
            [
                'name' => ['en' => 'Inventory', 'ar' => 'المخزون'],
                'description' => ['en' => 'Managing products and stock', 'ar' => 'إدارة المنتجات والمخزون'],
                'slug' => 'inventory',
                'icon' => 'heroicon-o-cube',
                'sort_order' => 6,
            ],
            [
                'name' => ['en' => 'Staff & HR', 'ar' => 'الموظفين والموارد البشرية'],
                'description' => ['en' => 'Managing staff and human resources', 'ar' => 'إدارة الموظفين والموارد البشرية'],
                'slug' => 'staff-hr',
                'icon' => 'heroicon-o-user-group',
                'sort_order' => 7,
            ],
            [
                'name' => ['en' => 'Reports & Analytics', 'ar' => 'التقارير والتحليلات'],
                'description' => ['en' => 'Understanding reports and data analytics', 'ar' => 'فهم التقارير وتحليل البيانات'],
                'slug' => 'reports-analytics',
                'icon' => 'heroicon-o-chart-bar',
                'sort_order' => 8,
            ],
            [
                'name' => ['en' => 'Settings', 'ar' => 'الإعدادات'],
                'description' => ['en' => 'System configuration and settings', 'ar' => 'إعدادات وتكوين النظام'],
                'slug' => 'settings',
                'icon' => 'heroicon-o-cog-6-tooth',
                'sort_order' => 9,
            ],
            [
                'name' => ['en' => 'Troubleshooting', 'ar' => 'استكشاف الأخطاء'],
                'description' => ['en' => 'Common issues and how to resolve them', 'ar' => 'المشاكل الشائعة وكيفية حلها'],
                'slug' => 'troubleshooting',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'sort_order' => 10,
            ],
        ];

        foreach ($categories as $categoryData) {
            HelpCategory::firstOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }
    }
}
