<?php

namespace Modules\Projects\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\ProjectTag;

class DefaultProjectTagsSeeder extends Seeder
{
    public function run(): void
    {
        // Get tenant from various sources
        $tenant = app('currentTenant') ?? null;

        if (!$tenant) {
            try {
                $tenant = app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->current();
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $tenantId = $tenant?->id;

        if (!$tenantId) {
            $this->command?->warn('No tenant context found, skipping DefaultProjectTagsSeeder');
            return;
        }

        // Define default tags
        $tags = [
            ['name' => ['en' => 'Bug', 'ar' => 'خطأ'], 'color' => '#EF4444'], // Red
            ['name' => ['en' => 'Feature', 'ar' => 'ميزة'], 'color' => '#3B82F6'], // Blue
            ['name' => ['en' => 'Enhancement', 'ar' => 'تحسين'], 'color' => '#10B981'], // Green
            ['name' => ['en' => 'Documentation', 'ar' => 'توثيق'], 'color' => '#8B5CF6'], // Purple
            ['name' => ['en' => 'Urgent', 'ar' => 'عاجل'], 'color' => '#F97316'], // Orange
            ['name' => ['en' => 'Research', 'ar' => 'بحث'], 'color' => '#6366F1'], // Indigo
            ['name' => ['en' => 'Design', 'ar' => 'تصميم'], 'color' => '#EC4899'], // Pink
            ['name' => ['en' => 'Testing', 'ar' => 'اختبار'], 'color' => '#14B8A6'], // Teal
        ];

        foreach ($tags as $tagData) {
            DB::table('project_tags')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'name' => json_encode($tagData['name']),
                ],
                [
                    'tenant_id' => $tenantId,
                    'name' => json_encode($tagData['name']),
                    'color' => $tagData['color'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command?->info('Default project tags created successfully');
    }
}
