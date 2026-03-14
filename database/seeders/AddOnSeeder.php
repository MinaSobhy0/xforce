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
                'sort_order' => 4,
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
