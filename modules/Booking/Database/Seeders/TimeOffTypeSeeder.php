<?php

namespace Modules\Booking\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Booking\Models\TimeOffType;

class TimeOffTypeSeeder extends Seeder
{
    /**
     * Resolve the tenant ID from various sources.
     */
    protected function resolveTenantId(): ?string
    {
        // Try TenantManager first
        try {
            $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
            if ($tenantManager->current()) {
                return $tenantManager->current()->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Try app('currentTenant')
        try {
            if ($tenant = app('currentTenant')) {
                return $tenant->id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Try to resolve from database search_path (for CLI seeding)
        foreach (['pgsql', 'tenant'] as $conn) {
            try {
                $result = \DB::connection($conn)->select('SHOW search_path');
                $searchPath = $result[0]->search_path ?? 'public';

                if (preg_match('/tenant[_-]([^,\s"]+)/', $searchPath, $matches)) {
                    $slug = str_replace('_', '-', $matches[1]);

                    $tenant = \DB::connection('pgsql')
                        ->table('public.tenants')
                        ->where('slug', $slug)
                        ->orWhere('slug', $matches[1])
                        ->first();

                    if ($tenant) {
                        return $tenant->id;
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        return null;
    }

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();

        $types = [
            [
                'code' => 'ANNUAL',
                'name' => ['en' => 'Annual Leave', 'ar' => 'إجازة سنوية'],
                'description' => ['en' => 'Paid annual vacation leave', 'ar' => 'إجازة سنوية مدفوعة'],
                'color' => 'success',
                'is_paid' => true,
                'requires_approval' => true,
                'default_days_per_year' => 21,
                'max_days_per_request' => 14,
                'min_days_notice' => 7,
                'allow_half_day' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'SICK',
                'name' => ['en' => 'Sick Leave', 'ar' => 'إجازة مرضية'],
                'description' => ['en' => 'Paid sick leave with medical certificate', 'ar' => 'إجازة مرضية مدفوعة بشهادة طبية'],
                'color' => 'danger',
                'is_paid' => true,
                'requires_approval' => true,
                'default_days_per_year' => 15,
                'max_days_per_request' => 7,
                'min_days_notice' => 0,
                'allow_half_day' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'PERSONAL',
                'name' => ['en' => 'Personal Leave', 'ar' => 'إجازة شخصية'],
                'description' => ['en' => 'Paid leave for personal matters', 'ar' => 'إجازة مدفوعة للأمور الشخصية'],
                'color' => 'info',
                'is_paid' => true,
                'requires_approval' => true,
                'default_days_per_year' => 5,
                'max_days_per_request' => 2,
                'min_days_notice' => 1,
                'allow_half_day' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'MATERNITY',
                'name' => ['en' => 'Maternity Leave', 'ar' => 'إجازة أمومة'],
                'description' => ['en' => 'Paid maternity leave', 'ar' => 'إجازة أمومة مدفوعة'],
                'color' => 'warning',
                'is_paid' => true,
                'requires_approval' => true,
                'default_days_per_year' => 90,
                'max_days_per_request' => 90,
                'min_days_notice' => 30,
                'allow_half_day' => false,
                'sort_order' => 4,
            ],
            [
                'code' => 'PATERNITY',
                'name' => ['en' => 'Paternity Leave', 'ar' => 'إجازة أبوة'],
                'description' => ['en' => 'Paid paternity leave', 'ar' => 'إجازة أبوة مدفوعة'],
                'color' => 'primary',
                'is_paid' => true,
                'requires_approval' => true,
                'default_days_per_year' => 3,
                'max_days_per_request' => 3,
                'min_days_notice' => 7,
                'allow_half_day' => false,
                'sort_order' => 5,
            ],
            [
                'code' => 'BEREAVEMENT',
                'name' => ['en' => 'Bereavement Leave', 'ar' => 'إجازة وفاة'],
                'description' => ['en' => 'Paid leave for family bereavement', 'ar' => 'إجازة مدفوعة لوفاة أحد الأقارب'],
                'color' => 'gray',
                'is_paid' => true,
                'requires_approval' => true,
                'default_days_per_year' => 5,
                'max_days_per_request' => 5,
                'min_days_notice' => 0,
                'allow_half_day' => false,
                'sort_order' => 6,
            ],
            [
                'code' => 'UNPAID',
                'name' => ['en' => 'Unpaid Leave', 'ar' => 'إجازة بدون راتب'],
                'description' => ['en' => 'Unpaid leave of absence', 'ar' => 'إجازة بدون راتب'],
                'color' => 'gray',
                'is_paid' => false,
                'requires_approval' => true,
                'default_days_per_year' => 30,
                'max_days_per_request' => 30,
                'min_days_notice' => 14,
                'allow_half_day' => false,
                'sort_order' => 7,
            ],
            [
                'code' => 'TRAINING',
                'name' => ['en' => 'Training Leave', 'ar' => 'إجازة تدريب'],
                'description' => ['en' => 'Paid leave for training or conferences', 'ar' => 'إجازة مدفوعة للتدريب أو المؤتمرات'],
                'color' => 'info',
                'is_paid' => true,
                'requires_approval' => true,
                'default_days_per_year' => 10,
                'max_days_per_request' => 5,
                'min_days_notice' => 14,
                'allow_half_day' => false,
                'sort_order' => 8,
            ],
        ];

        foreach ($types as $type) {
            $existing = TimeOffType::where('code', $type['code'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$existing) {
                TimeOffType::create(array_merge($type, [
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                    'allow_partial_day' => false,
                ]));
            }
        }
    }
}
