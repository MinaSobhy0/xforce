<?php

namespace Database\Seeders;

use App\Models\AddOn;
use Illuminate\Database\Seeder;

class AddOnSeeder extends Seeder
{
    /**
     * Price multipliers relative to EGP base price.
     * Based on approximate exchange rates and market conditions.
     */
    private const PRICE_MULTIPLIERS = [
        'EG' => 1.0,      // Base price in EGP
        'SA' => 0.075,    // ~1 EGP = 0.075 SAR
        'AE' => 0.073,    // ~1 EGP = 0.073 AED
        'KW' => 0.006,    // ~1 EGP = 0.006 KWD
        'QA' => 0.073,    // ~1 EGP = 0.073 QAR
        'BH' => 0.0075,   // ~1 EGP = 0.0075 BHD
        'OM' => 0.0077,   // ~1 EGP = 0.0077 OMR
        'JO' => 0.014,    // ~1 EGP = 0.014 JOD
        'LB' => 0.02,     // ~1 EGP = 0.02 USD (Lebanon uses USD)
    ];

    private const CURRENCIES = [
        'EG' => 'EGP',
        'SA' => 'SAR',
        'AE' => 'AED',
        'KW' => 'KWD',
        'QA' => 'QAR',
        'BH' => 'BHD',
        'OM' => 'OMR',
        'JO' => 'JOD',
        'LB' => 'USD',
    ];

    public function run(): void
    {
        $addOns = [
            [
                'code' => 'EXTRA_USERS_5',
                'name' => '5 Extra Users Pack',
                'name_ar' => 'حزمة 5 مستخدمين إضافيين',
                'description' => 'Add 5 additional users to your plan',
                'description_ar' => 'أضف 5 مستخدمين إضافيين لخطتك',
                'icon' => 'heroicon-o-user-plus',
                'base_monthly' => 250,
                'base_yearly' => 2500,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => ['5 additional user accounts', 'Full access to all modules'],
                'limits' => ['users' => 5],
                'sort_order' => 1,
            ],
            [
                'code' => 'EXTRA_USERS_10',
                'name' => '10 Extra Users Pack',
                'name_ar' => 'حزمة 10 مستخدمين إضافيين',
                'description' => 'Add 10 additional users to your plan',
                'description_ar' => 'أضف 10 مستخدمين إضافيين لخطتك',
                'icon' => 'heroicon-o-user-plus',
                'base_monthly' => 450,
                'base_yearly' => 4500,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => ['10 additional user accounts', 'Full access to all modules'],
                'limits' => ['users' => 10],
                'sort_order' => 2,
            ],
            [
                'code' => 'EXTRA_BRANCH',
                'name' => 'Additional Branch',
                'name_ar' => 'فرع إضافي',
                'description' => 'Add one additional branch location',
                'description_ar' => 'أضف فرع إضافي واحد',
                'icon' => 'heroicon-o-building-storefront',
                'base_monthly' => 200,
                'base_yearly' => 2000,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => ['1 additional branch', 'Separate inventory tracking', 'Branch-specific reports'],
                'limits' => ['branches' => 1],
                'sort_order' => 3,
            ],
            [
                'code' => 'WHATSAPP_INTEGRATION',
                'name' => 'WhatsApp Integration',
                'name_ar' => 'تكامل واتساب',
                'description' => 'Send appointment reminders and notifications via WhatsApp',
                'description_ar' => 'إرسال تذكيرات المواعيد والإشعارات عبر واتساب',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'base_monthly' => 150,
                'base_yearly' => 1500,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => [
                    'Appointment reminders',
                    'Booking confirmations',
                    'Custom message templates',
                    'Two-way messaging',
                ],
                'limits' => ['whatsapp_messages' => 1000],
                'sort_order' => 4,
            ],
            [
                'code' => 'SMS_PACK_1000',
                'name' => 'SMS Pack (1000 Messages)',
                'name_ar' => 'حزمة رسائل SMS (1000 رسالة)',
                'description' => '1000 SMS messages for patient notifications',
                'description_ar' => '1000 رسالة SMS لإشعارات المرضى',
                'icon' => 'heroicon-o-device-phone-mobile',
                'base_monthly' => 100,
                'base_yearly' => 1000,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => ['1000 SMS messages', 'Appointment reminders', 'Custom templates'],
                'limits' => ['sms_messages' => 1000],
                'sort_order' => 5,
            ],
            [
                'code' => 'SMS_PACK_5000',
                'name' => 'SMS Pack (5000 Messages)',
                'name_ar' => 'حزمة رسائل SMS (5000 رسالة)',
                'description' => '5000 SMS messages for patient notifications',
                'description_ar' => '5000 رسالة SMS لإشعارات المرضى',
                'icon' => 'heroicon-o-device-phone-mobile',
                'base_monthly' => 400,
                'base_yearly' => 4000,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => ['5000 SMS messages', 'Appointment reminders', 'Custom templates', 'Priority delivery'],
                'limits' => ['sms_messages' => 5000],
                'sort_order' => 6,
            ],
            [
                'code' => 'ADVANCED_REPORTS',
                'name' => 'Advanced Reports & Analytics',
                'name_ar' => 'التقارير والتحليلات المتقدمة',
                'description' => 'Detailed analytics, custom reports, and data export',
                'description_ar' => 'تحليلات تفصيلية وتقارير مخصصة وتصدير البيانات',
                'icon' => 'heroicon-o-chart-bar',
                'base_monthly' => 100,
                'base_yearly' => 1000,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => [
                    'Custom report builder',
                    'Advanced analytics dashboard',
                    'Excel/PDF export',
                    'Scheduled reports',
                    'Revenue forecasting',
                ],
                'limits' => null,
                'sort_order' => 7,
            ],
            [
                'code' => 'ONLINE_BOOKING',
                'name' => 'Online Booking Portal',
                'name_ar' => 'بوابة الحجز الإلكتروني',
                'description' => 'Let patients book appointments online 24/7',
                'description_ar' => 'اسمح للمرضى بحجز المواعيد عبر الإنترنت على مدار الساعة',
                'icon' => 'heroicon-o-calendar-days',
                'base_monthly' => 150,
                'base_yearly' => 1500,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => [
                    'Patient self-booking',
                    'Real-time availability',
                    'Automatic confirmations',
                    'Custom booking page',
                    'Integration with website',
                ],
                'limits' => null,
                'sort_order' => 8,
            ],
            [
                'code' => 'PATIENT_PORTAL',
                'name' => 'Patient Portal',
                'name_ar' => 'بوابة المرضى',
                'description' => 'Give patients access to their records, appointments, and invoices',
                'description_ar' => 'امنح المرضى الوصول إلى سجلاتهم ومواعيدهم وفواتيرهم',
                'icon' => 'heroicon-o-user-circle',
                'base_monthly' => 200,
                'base_yearly' => 2000,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => [
                    'Patient login',
                    'View medical history',
                    'Download invoices',
                    'Book appointments',
                    'Secure messaging',
                ],
                'limits' => null,
                'sort_order' => 9,
            ],
            [
                'code' => 'EXTRA_STORAGE_10GB',
                'name' => 'Extra Storage (10 GB)',
                'name_ar' => 'مساحة تخزين إضافية (10 جيجابايت)',
                'description' => 'Add 10 GB of storage for photos and documents',
                'description_ar' => 'أضف 10 جيجابايت من مساحة التخزين للصور والمستندات',
                'icon' => 'heroicon-o-server-stack',
                'base_monthly' => 50,
                'base_yearly' => 500,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => ['10 GB additional storage', 'Photos & documents', 'Automatic backup'],
                'limits' => ['storage_mb' => 10240],
                'sort_order' => 10,
            ],
            [
                'code' => 'MARKETING_MODULE',
                'name' => 'Marketing & Campaigns',
                'name_ar' => 'التسويق والحملات',
                'description' => 'Email campaigns, promotions, and loyalty programs',
                'description_ar' => 'حملات البريد الإلكتروني والعروض الترويجية وبرامج الولاء',
                'icon' => 'heroicon-o-megaphone',
                'base_monthly' => 150,
                'base_yearly' => 1500,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => [
                    'Email campaigns',
                    'SMS campaigns',
                    'Birthday greetings',
                    'Promotional offers',
                    'Campaign analytics',
                ],
                'limits' => ['campaign_emails' => 5000],
                'sort_order' => 11,
            ],
            [
                'code' => 'API_ACCESS',
                'name' => 'API Access',
                'name_ar' => 'الوصول لواجهة البرمجة',
                'description' => 'Full API access for custom integrations',
                'description_ar' => 'وصول كامل لواجهة البرمجة للتكاملات المخصصة',
                'icon' => 'heroicon-o-code-bracket',
                'base_monthly' => 300,
                'base_yearly' => 3000,
                'is_active' => true,
                'is_recurring' => true,
                'billing_interval' => 'monthly',
                'features' => [
                    'RESTful API access',
                    'Webhooks',
                    'API documentation',
                    'Developer support',
                    '10,000 API calls/month',
                ],
                'limits' => ['api_calls' => 10000],
                'sort_order' => 12,
            ],
        ];

        foreach ($addOns as $addOn) {
            // Generate country-specific prices
            $prices = $this->generateCountryPrices($addOn['base_monthly'], $addOn['base_yearly']);

            AddOn::updateOrCreate(
                ['code' => $addOn['code']],
                [
                    'name' => $addOn['name'],
                    'name_ar' => $addOn['name_ar'],
                    'description' => $addOn['description'],
                    'description_ar' => $addOn['description_ar'],
                    'icon' => $addOn['icon'],
                    'monthly_price' => $addOn['base_monthly'], // Keep legacy for backwards compatibility
                    'yearly_price' => $addOn['base_yearly'],
                    'prices' => $prices,
                    'is_active' => $addOn['is_active'],
                    'is_recurring' => $addOn['is_recurring'],
                    'billing_interval' => $addOn['billing_interval'],
                    'features' => $addOn['features'],
                    'limits' => $addOn['limits'],
                    'sort_order' => $addOn['sort_order'],
                ]
            );
        }

        $this->command->info('Add-ons seeded successfully: ' . count($addOns) . ' add-ons created/updated with country-specific pricing.');
    }

    /**
     * Generate prices for all supported countries based on EGP base price.
     */
    private function generateCountryPrices(float $baseMonthly, float $baseYearly): array
    {
        $prices = [];

        foreach (self::PRICE_MULTIPLIERS as $country => $multiplier) {
            $prices[$country] = [
                'monthly' => round($baseMonthly * $multiplier, 2),
                'yearly' => round($baseYearly * $multiplier, 2),
                'currency' => self::CURRENCIES[$country],
            ];
        }

        return $prices;
    }
}
