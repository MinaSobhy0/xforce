<?php

namespace Modules\Assets\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assets\Models\AssetType;
use Modules\Accounting\Models\ChartOfAccount;

class AssetTypeSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            $this->command->warn('No tenant context. Skipping asset type seeder.');
            return;
        }

        // Try to find GL accounts
        $fixedAssetAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', ChartOfAccount::TYPE_FIXED_ASSET)
            ->where('is_active', true)
            ->first();

        $depreciationExpenseAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereIn('type', [ChartOfAccount::TYPE_DEPRECIATION, ChartOfAccount::TYPE_EXPENSE])
            ->where('is_active', true)
            ->first();

        $accumulatedDepreciationAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', ChartOfAccount::TYPE_FIXED_ASSET)
            ->where('is_active', true)
            ->where('id', '!=', $fixedAssetAccount?->id)
            ->first() ?? $fixedAssetAccount;

        $gainLossAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereIn('type', [ChartOfAccount::TYPE_OTHER_INCOME, ChartOfAccount::TYPE_EXPENSE])
            ->where('is_active', true)
            ->first();

        $assetTypes = [
            [
                'name' => ['en' => 'Computers & IT Equipment', 'ar' => 'أجهزة الكمبيوتر ومعدات تكنولوجيا المعلومات'],
                'description' => ['en' => 'Laptops, desktops, servers, and networking equipment', 'ar' => 'أجهزة الكمبيوتر المحمولة والمكتبية والخوادم ومعدات الشبكات'],
                'depreciation_method' => AssetType::METHOD_STRAIGHT_LINE,
                'useful_life_years' => 3,
                'salvage_value_percent' => 10,
            ],
            [
                'name' => ['en' => 'Medical Equipment', 'ar' => 'المعدات الطبية'],
                'description' => ['en' => 'Diagnostic and treatment medical devices', 'ar' => 'أجهزة التشخيص والعلاج الطبية'],
                'depreciation_method' => AssetType::METHOD_STRAIGHT_LINE,
                'useful_life_years' => 7,
                'salvage_value_percent' => 5,
            ],
            [
                'name' => ['en' => 'Office Furniture', 'ar' => 'أثاث المكتب'],
                'description' => ['en' => 'Desks, chairs, cabinets, and office fixtures', 'ar' => 'المكاتب والكراسي والخزائن وتجهيزات المكتب'],
                'depreciation_method' => AssetType::METHOD_STRAIGHT_LINE,
                'useful_life_years' => 7,
                'salvage_value_percent' => 10,
            ],
            [
                'name' => ['en' => 'Vehicles', 'ar' => 'المركبات'],
                'description' => ['en' => 'Cars, vans, and transport vehicles', 'ar' => 'السيارات والشاحنات الصغيرة ومركبات النقل'],
                'depreciation_method' => AssetType::METHOD_DECLINING_BALANCE,
                'useful_life_years' => 5,
                'salvage_value_percent' => 15,
                'declining_balance_rate' => 40,
            ],
            [
                'name' => ['en' => 'Building Improvements', 'ar' => 'تحسينات المباني'],
                'description' => ['en' => 'Leasehold improvements and building modifications', 'ar' => 'تحسينات الإيجار وتعديلات المباني'],
                'depreciation_method' => AssetType::METHOD_STRAIGHT_LINE,
                'useful_life_years' => 10,
                'salvage_value_percent' => 0,
            ],
            [
                'name' => ['en' => 'Air Conditioning & HVAC', 'ar' => 'التكييف والتدفئة والتهوية'],
                'description' => ['en' => 'Air conditioning units and HVAC systems', 'ar' => 'وحدات التكييف وأنظمة التدفئة والتهوية'],
                'depreciation_method' => AssetType::METHOD_STRAIGHT_LINE,
                'useful_life_years' => 10,
                'salvage_value_percent' => 5,
            ],
            [
                'name' => ['en' => 'Software & Licenses', 'ar' => 'البرمجيات والتراخيص'],
                'description' => ['en' => 'Software licenses and digital assets', 'ar' => 'تراخيص البرامج والأصول الرقمية'],
                'depreciation_method' => AssetType::METHOD_STRAIGHT_LINE,
                'useful_life_years' => 3,
                'salvage_value_percent' => 0,
            ],
            [
                'name' => ['en' => 'Lab Equipment', 'ar' => 'معدات المختبر'],
                'description' => ['en' => 'Laboratory instruments and testing equipment', 'ar' => 'أدوات المختبر ومعدات الاختبار'],
                'depreciation_method' => AssetType::METHOD_STRAIGHT_LINE,
                'useful_life_years' => 5,
                'salvage_value_percent' => 5,
            ],
        ];

        foreach ($assetTypes as $data) {
            $existing = AssetType::where('tenant_id', $tenantId)
                ->whereRaw("name->>'en' = ?", [$data['name']['en']])
                ->first();

            if ($existing) {
                continue;
            }

            AssetType::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'description' => $data['description'],
                'depreciation_method' => $data['depreciation_method'],
                'useful_life_years' => $data['useful_life_years'],
                'salvage_value_percent' => $data['salvage_value_percent'],
                'declining_balance_rate' => $data['declining_balance_rate'] ?? null,
                'fixed_asset_account_id' => $fixedAssetAccount?->id,
                'accumulated_depreciation_account_id' => $accumulatedDepreciationAccount?->id,
                'depreciation_expense_account_id' => $depreciationExpenseAccount?->id,
                'gain_loss_account_id' => $gainLossAccount?->id,
                'auto_create_on_purchase' => true,
                'is_active' => true,
            ]);
        }

        $this->command->info('Asset types seeded successfully.');
    }
}
