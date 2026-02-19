<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // Core modules
            ['code' => 'core', 'name' => ['en' => 'Core', 'ar' => 'النواة'], 'category' => 'core', 'icon_emoji' => '🔒', 'tier' => 'free', 'is_core' => true, 'sort_order' => 1],
            ['code' => 'auth', 'name' => ['en' => 'Auth & RBAC', 'ar' => 'المصادقة والصلاحيات'], 'category' => 'core', 'icon_emoji' => '🔒', 'tier' => 'free', 'is_core' => true, 'sort_order' => 2],

            // Operations
            ['code' => 'patients', 'name' => ['en' => 'Patients', 'ar' => 'المرضى'], 'category' => 'operations', 'icon_emoji' => '👤', 'tier' => 'free', 'sort_order' => 10],
            ['code' => 'booking', 'name' => ['en' => 'Booking', 'ar' => 'الحجوزات'], 'category' => 'operations', 'icon_emoji' => '📅', 'tier' => 'free', 'sort_order' => 11],
            ['code' => 'treatments', 'name' => ['en' => 'Treatments', 'ar' => 'العلاجات'], 'category' => 'operations', 'icon_emoji' => '💆', 'tier' => 'free', 'sort_order' => 12],
            ['code' => 'equipment', 'name' => ['en' => 'Equipment', 'ar' => 'المعدات'], 'category' => 'operations', 'icon_emoji' => '🔧', 'tier' => 'starter', 'sort_order' => 13],
            ['code' => 'inventory', 'name' => ['en' => 'Inventory', 'ar' => 'المخزون'], 'category' => 'operations', 'icon_emoji' => '📦', 'tier' => 'professional', 'sort_order' => 14],

            // Financial
            ['code' => 'billing', 'name' => ['en' => 'Billing', 'ar' => 'الفواتير'], 'category' => 'financial', 'icon_emoji' => '💰', 'tier' => 'free', 'sort_order' => 20],
            ['code' => 'accounting', 'name' => ['en' => 'Accounting', 'ar' => 'المحاسبة'], 'category' => 'financial', 'icon_emoji' => '📊', 'tier' => 'professional', 'sort_order' => 21],
            ['code' => 'payroll', 'name' => ['en' => 'Payroll', 'ar' => 'الرواتب'], 'category' => 'financial', 'icon_emoji' => '💵', 'tier' => 'professional', 'sort_order' => 22],

            // Sales
            ['code' => 'packages', 'name' => ['en' => 'Packages', 'ar' => 'الباقات'], 'category' => 'sales', 'icon_emoji' => '📦', 'tier' => 'professional', 'sort_order' => 30],
            ['code' => 'giftcards', 'name' => ['en' => 'Gift Cards', 'ar' => 'بطاقات الهدايا'], 'category' => 'sales', 'icon_emoji' => '🎁', 'tier' => 'professional', 'sort_order' => 31],
            ['code' => 'memberships', 'name' => ['en' => 'Memberships', 'ar' => 'العضويات'], 'category' => 'sales', 'icon_emoji' => '⭐', 'tier' => 'professional', 'sort_order' => 32],
            ['code' => 'loyalty', 'name' => ['en' => 'Loyalty', 'ar' => 'نقاط الولاء'], 'category' => 'sales', 'icon_emoji' => '🎯', 'tier' => 'professional', 'sort_order' => 33],

            // Staff
            ['code' => 'staff', 'name' => ['en' => 'Staff', 'ar' => 'الموظفين'], 'category' => 'operations', 'icon_emoji' => '👥', 'tier' => 'professional', 'sort_order' => 40],

            // Marketing
            ['code' => 'whatsapp', 'name' => ['en' => 'WhatsApp', 'ar' => 'واتساب'], 'category' => 'marketing', 'icon_emoji' => '💬', 'tier' => 'professional', 'sort_order' => 50],
            ['code' => 'sms', 'name' => ['en' => 'SMS', 'ar' => 'رسائل نصية'], 'category' => 'marketing', 'icon_emoji' => '📱', 'tier' => 'professional', 'sort_order' => 51],
            ['code' => 'email', 'name' => ['en' => 'Email Marketing', 'ar' => 'التسويق بالبريد'], 'category' => 'marketing', 'icon_emoji' => '📧', 'tier' => 'professional', 'sort_order' => 52],
            ['code' => 'social', 'name' => ['en' => 'Social Media', 'ar' => 'وسائل التواصل'], 'category' => 'marketing', 'icon_emoji' => '📸', 'tier' => 'addon', 'addon_price_monthly_minor' => 29900, 'sort_order' => 53],

            // Advanced
            ['code' => 'reporting', 'name' => ['en' => 'Reporting', 'ar' => 'التقارير'], 'category' => 'advanced', 'icon_emoji' => '📈', 'tier' => 'professional', 'sort_order' => 60],
            ['code' => 'portal', 'name' => ['en' => 'Patient Portal', 'ar' => 'بوابة المرضى'], 'category' => 'advanced', 'icon_emoji' => '🌐', 'tier' => 'addon', 'addon_price_monthly_minor' => 49900, 'sort_order' => 61],
            ['code' => 'api', 'name' => ['en' => 'API Access', 'ar' => 'واجهة برمجية'], 'category' => 'advanced', 'icon_emoji' => '🔌', 'tier' => 'enterprise', 'sort_order' => 62],
            ['code' => 'ai', 'name' => ['en' => 'AI Recommendations', 'ar' => 'توصيات الذكاء الاصطناعي'], 'category' => 'advanced', 'icon_emoji' => '🤖', 'tier' => 'enterprise', 'is_beta' => true, 'sort_order' => 63],
        ];

        foreach ($modules as $module) {
            $data = array_merge([
                'description' => null,
                'is_core' => false,
                'is_active' => true,
                'is_beta' => false,
                'addon_price_monthly_minor' => null,
                'dependencies' => null,
                'settings_schema' => null,
            ], $module);

            Module::updateOrCreate(
                ['code' => $module['code']],
                $data
            );
        }
    }
}
