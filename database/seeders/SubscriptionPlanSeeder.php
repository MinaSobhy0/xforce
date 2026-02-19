<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => ['en' => 'Starter', 'ar' => 'المبتدئ'],
                'description' => ['en' => 'Perfect for small clinics', 'ar' => 'مثالي للعيادات الصغيرة'],
                'price_monthly_minor' => 99900, // 999 EGP
                'price_yearly_minor' => 999000, // 9,990 EGP (17% savings)
                'currency' => 'EGP',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'max_users' => 5,
                'max_branches' => 1,
                'max_patients' => 500,
                'max_storage_mb' => 2048, // 2 GB
                'max_equipment' => 10,
                'max_products' => 50,
                'max_treatments' => 30,
                'max_appointments_monthly' => 200,
                'max_whatsapp_monthly' => 500,
                'max_sms_monthly' => 200,
                'max_emails_monthly' => 1000,
                'overage_appointment_minor' => 300, // 3 EGP
                'overage_whatsapp_minor' => 35, // 0.35 EGP
                'overage_sms_minor' => 15, // 0.15 EGP
                'allow_white_label' => false,
                'allow_custom_domain' => false,
                'allow_data_export' => true,
                'allow_api_access' => false,
                'has_priority_support' => false,
                'data_retention_days' => 365,
                'max_concurrent_sessions' => 3,
                'included_module_codes' => ['core', 'auth', 'patients', 'booking', 'billing', 'equipment'],
            ],
            [
                'code' => 'professional',
                'name' => ['en' => 'Professional', 'ar' => 'الاحترافي'],
                'description' => ['en' => 'For growing clinics', 'ar' => 'للعيادات المتنامية'],
                'price_monthly_minor' => 249900, // 2,499 EGP
                'price_yearly_minor' => 2499000, // 24,990 EGP
                'currency' => 'EGP',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'max_users' => 20,
                'max_branches' => 3,
                'max_patients' => 5000,
                'max_storage_mb' => 20480, // 20 GB
                'max_equipment' => 50,
                'max_products' => 200,
                'max_treatments' => 100,
                'max_appointments_monthly' => 2000,
                'max_whatsapp_monthly' => 5000,
                'max_sms_monthly' => 2000,
                'max_emails_monthly' => 10000,
                'overage_appointment_minor' => 300,
                'overage_whatsapp_minor' => 35,
                'overage_sms_minor' => 15,
                'allow_white_label' => false,
                'allow_custom_domain' => true,
                'allow_data_export' => true,
                'allow_api_access' => true,
                'has_priority_support' => false,
                'data_retention_days' => 730, // 2 years
                'max_concurrent_sessions' => 10,
                'included_module_codes' => ['core', 'auth', 'patients', 'booking', 'treatments', 'equipment', 'billing', 'accounting', 'packages', 'giftcards', 'memberships', 'inventory', 'staff', 'payroll', 'loyalty', 'whatsapp', 'sms', 'email', 'reporting'],
            ],
            [
                'code' => 'enterprise',
                'name' => ['en' => 'Enterprise', 'ar' => 'المؤسسات'],
                'description' => ['en' => 'For large clinic chains', 'ar' => 'لسلاسل العيادات الكبيرة'],
                'price_monthly_minor' => 499900, // 4,999 EGP
                'price_yearly_minor' => 4999000, // 49,990 EGP
                'currency' => 'EGP',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'max_users' => null, // Unlimited
                'max_branches' => null, // Unlimited
                'max_patients' => null, // Unlimited
                'max_storage_mb' => 102400, // 100 GB
                'max_equipment' => null,
                'max_products' => null,
                'max_treatments' => null,
                'max_appointments_monthly' => null,
                'max_whatsapp_monthly' => 20000,
                'max_sms_monthly' => 10000,
                'max_emails_monthly' => null,
                'overage_whatsapp_minor' => 30,
                'overage_sms_minor' => 12,
                'allow_white_label' => true,
                'allow_custom_domain' => true,
                'allow_data_export' => true,
                'allow_api_access' => true,
                'has_priority_support' => true,
                'data_retention_days' => 1825, // 5 years
                'max_concurrent_sessions' => null, // Unlimited
                'included_module_codes' => ['core', 'auth', 'patients', 'booking', 'treatments', 'equipment', 'billing', 'accounting', 'packages', 'giftcards', 'memberships', 'inventory', 'staff', 'payroll', 'loyalty', 'whatsapp', 'sms', 'email', 'social', 'reporting', 'portal', 'api'],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }
    }
}
