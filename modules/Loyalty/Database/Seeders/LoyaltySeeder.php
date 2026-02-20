<?php

namespace Modules\Loyalty\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Loyalty\Models\LoyaltyRule;
use Modules\Loyalty\Models\ReferralProgram;

class LoyaltySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDefaultRules();
        $this->seedDefaultReferralProgram();
    }

    protected function seedDefaultRules(): void
    {
        // Per Spend Rule - 1 point per 10 EGP spent
        LoyaltyRule::firstOrCreate(
            ['type' => LoyaltyRule::TYPE_PER_SPEND],
            [
                'name' => [
                    'en' => 'Points per Spend',
                    'ar' => 'نقاط لكل إنفاق',
                ],
                'description' => [
                    'en' => 'Earn 1 point for every 10 EGP spent',
                    'ar' => 'اكسب نقطة واحدة لكل 10 جنيه تنفقها',
                ],
                'type' => LoyaltyRule::TYPE_PER_SPEND,
                'points_per_currency_unit' => 0.1, // 1 point per 10 EGP
                'min_spend_minor' => 10000, // Minimum 100 EGP
                'multiplier' => 1.0,
                'is_active' => true,
                'priority' => 10,
            ]
        );

        // Per Visit Rule - 10 points per completed appointment
        LoyaltyRule::firstOrCreate(
            ['type' => LoyaltyRule::TYPE_PER_VISIT],
            [
                'name' => [
                    'en' => 'Visit Bonus',
                    'ar' => 'مكافأة الزيارة',
                ],
                'description' => [
                    'en' => 'Earn 10 points for each completed visit',
                    'ar' => 'اكسب 10 نقاط لكل زيارة مكتملة',
                ],
                'type' => LoyaltyRule::TYPE_PER_VISIT,
                'points_amount' => 10,
                'multiplier' => 1.0,
                'is_active' => true,
                'priority' => 5,
            ]
        );

        // Birthday Rule - 100 points on birthday
        LoyaltyRule::firstOrCreate(
            ['type' => LoyaltyRule::TYPE_BIRTHDAY],
            [
                'name' => [
                    'en' => 'Birthday Bonus',
                    'ar' => 'مكافأة عيد الميلاد',
                ],
                'description' => [
                    'en' => 'Receive 100 bonus points on your birthday',
                    'ar' => 'احصل على 100 نقطة مكافأة في عيد ميلادك',
                ],
                'type' => LoyaltyRule::TYPE_BIRTHDAY,
                'points_amount' => 100,
                'multiplier' => 1.0,
                'is_active' => true,
                'priority' => 1,
            ]
        );

        // First Purchase Rule - 50 welcome points
        LoyaltyRule::firstOrCreate(
            ['type' => LoyaltyRule::TYPE_FIRST_PURCHASE],
            [
                'name' => [
                    'en' => 'Welcome Bonus',
                    'ar' => 'مكافأة الترحيب',
                ],
                'description' => [
                    'en' => 'Receive 50 welcome points on your first purchase',
                    'ar' => 'احصل على 50 نقطة ترحيبية عند أول شراء',
                ],
                'type' => LoyaltyRule::TYPE_FIRST_PURCHASE,
                'points_amount' => 50,
                'multiplier' => 1.0,
                'is_active' => true,
                'priority' => 1,
            ]
        );
    }

    protected function seedDefaultReferralProgram(): void
    {
        ReferralProgram::firstOrCreate(
            ['is_active' => true],
            [
                'name' => [
                    'en' => 'Refer a Friend',
                    'ar' => 'أحل صديقاً',
                ],
                'description' => [
                    'en' => 'Refer a friend and both of you get rewarded!',
                    'ar' => 'أحل صديقاً واحصلا معاً على المكافآت!',
                ],
                'referrer_points' => 100,
                'referred_points' => 50,
                'referrer_discount_percentage' => 10,
                'referred_discount_percentage' => 10,
                'min_purchase_minor' => 50000, // 500 EGP minimum
                'require_first_purchase' => true,
                'is_active' => true,
            ]
        );
    }
}
