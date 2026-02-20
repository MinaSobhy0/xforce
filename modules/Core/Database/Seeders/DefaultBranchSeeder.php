<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Tenancy\TenantManager;

class DefaultBranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a default "Main Branch" for the tenant.
     */
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);
        $tenant = $tenantManager->current();

        if (!$tenant) {
            $this->command?->warn('No tenant context. Skipping DefaultBranchSeeder.');
            return;
        }

        // Check if main branch already exists
        $existingMain = Branch::where('tenant_id', $tenant->id)
            ->where('is_main', true)
            ->first();

        if ($existingMain) {
            $this->command?->info('Main branch already exists. Skipping.');
            return;
        }

        // Create default main branch
        Branch::create([
            'tenant_id' => $tenant->id,
            'name' => $tenant->name ?? 'Main Branch',
            'code' => 'MAIN',
            'address' => $tenant->address ?? null,
            'city' => $tenant->city ?? null,
            'phone' => $tenant->phone ?? null,
            'email' => $tenant->email ?? null,
            'timezone' => $tenant->timezone ?? 'Africa/Cairo',
            'currency_code' => $tenant->currency ?? 'EGP',
            'working_hours' => $this->getDefaultWorkingHours(),
            'is_active' => true,
            'is_main' => true,
            'sort_order' => 0,
        ]);

        $this->command?->info('Default main branch created successfully.');
    }

    /**
     * Get default working hours (Sunday-Thursday 9am-6pm, Friday-Saturday closed).
     */
    protected function getDefaultWorkingHours(): array
    {
        return [
            ['day' => 'sunday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'monday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'tuesday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'wednesday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'thursday', 'open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => false],
            ['day' => 'friday', 'open_time' => null, 'close_time' => null, 'is_closed' => true],
            ['day' => 'saturday', 'open_time' => '10:00', 'close_time' => '16:00', 'is_closed' => false],
        ];
    }
}
