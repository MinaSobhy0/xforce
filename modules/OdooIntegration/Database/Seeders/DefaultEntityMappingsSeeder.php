<?php

namespace Modules\OdooIntegration\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooFieldMapping;
use Modules\OdooIntegration\Enums\SyncDirection;
use Modules\OdooIntegration\Enums\SyncFrequency;
use Modules\OdooIntegration\Enums\ConflictResolution;

class DefaultEntityMappingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // This seeder creates default entity mappings for each Odoo connection
        // It's meant to be run after a connection is created

        $connections = OdooConnection::where('is_active', true)->get();

        foreach ($connections as $connection) {
            $this->createDefaultMappings($connection);
        }
    }

    /**
     * Create default entity mappings for a connection.
     */
    public function createDefaultMappings(OdooConnection $connection): void
    {
        $defaultMappings = $this->getDefaultMappings();

        foreach ($defaultMappings as $mappingData) {
            // Skip if model class doesn't exist
            if (!class_exists($mappingData['local_model'])) {
                continue;
            }

            $mapping = OdooEntityMapping::firstOrCreate(
                [
                    'odoo_connection_id' => $connection->id,
                    'local_model' => $mappingData['local_model'],
                    'odoo_model' => $mappingData['odoo_model'],
                ],
                [
                    'tenant_id' => $connection->tenant_id,
                    'name' => $mappingData['name'],
                    'local_table' => $mappingData['local_table'],
                    'sync_direction' => $mappingData['sync_direction'],
                    'sync_frequency' => SyncFrequency::MANUAL,
                    'conflict_resolution' => ConflictResolution::MANUAL,
                    'batch_size' => 100,
                    'priority' => $mappingData['priority'],
                    'is_active' => false, // Disabled by default, user must enable
                ]
            );

            // Create field mappings
            $this->createFieldMappings($mapping, $mappingData['fields'] ?? []);
        }
    }

    /**
     * Create field mappings for an entity mapping.
     */
    protected function createFieldMappings(OdooEntityMapping $mapping, array $fields): void
    {
        $sortOrder = 0;

        foreach ($fields as $fieldData) {
            OdooFieldMapping::firstOrCreate(
                [
                    'entity_mapping_id' => $mapping->id,
                    'local_field' => $fieldData['local'],
                    'odoo_field' => $fieldData['odoo'],
                ],
                [
                    'direction' => $this->mapDirection($fieldData['direction'] ?? 'bidirectional'),
                    'transform_type' => $fieldData['transform'] ?? 'direct',
                    'transform_config' => $fieldData['config'] ?? null,
                    'is_required' => $fieldData['required'] ?? false,
                    'is_key_field' => $fieldData['is_key'] ?? false,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]
            );
        }
    }

    /**
     * Map direction string to enum.
     */
    protected function mapDirection(string $direction): SyncDirection
    {
        return match ($direction) {
            'import' => SyncDirection::IMPORT,
            'export' => SyncDirection::EXPORT,
            default => SyncDirection::BIDIRECTIONAL,
        };
    }

    /**
     * Get default mappings configuration.
     */
    protected function getDefaultMappings(): array
    {
        return [
            // Users
            [
                'name' => 'Users',
                'local_model' => 'Modules\\Auth\\Models\\User',
                'local_table' => 'users',
                'odoo_model' => 'res.users',
                'sync_direction' => SyncDirection::IMPORT,
                'priority' => 1,
                'fields' => [
                    ['local' => 'name', 'odoo' => 'name', 'direction' => 'import', 'transform' => 'direct'],
                    ['local' => 'email', 'odoo' => 'login', 'direction' => 'import', 'transform' => 'direct', 'is_key' => true],
                    ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'import', 'transform' => 'boolean'],
                ],
            ],

            // Staff Profiles
            [
                'name' => 'Staff Profiles',
                'local_model' => 'Modules\\Staff\\Models\\StaffProfile',
                'local_table' => 'staff_profiles',
                'odoo_model' => 'hr.employee',
                'sync_direction' => SyncDirection::IMPORT,
                'priority' => 2,
                'fields' => [
                    ['local' => 'user_id', 'odoo' => 'user_id', 'direction' => 'import', 'transform' => 'relation', 'config' => ['model' => 'Modules\\Auth\\Models\\User', 'odoo_model' => 'res.users']],
                    ['local' => 'first_name', 'odoo' => 'name', 'direction' => 'import', 'transform' => 'split_name', 'config' => ['part' => 'first']],
                    ['local' => 'last_name', 'odoo' => 'name', 'direction' => 'import', 'transform' => 'split_name', 'config' => ['part' => 'last']],
                    ['local' => 'work_email', 'odoo' => 'work_email', 'direction' => 'import', 'transform' => 'direct', 'is_key' => true],
                    ['local' => 'work_phone', 'odoo' => 'work_phone', 'direction' => 'import', 'transform' => 'direct'],
                    ['local' => 'mobile_phone', 'odoo' => 'mobile_phone', 'direction' => 'import', 'transform' => 'direct'],
                    ['local' => 'job_title', 'odoo' => 'job_title', 'direction' => 'import', 'transform' => 'direct'],
                    ['local' => 'hire_date', 'odoo' => 'date_start', 'direction' => 'import', 'transform' => 'date'],
                    ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'import', 'transform' => 'boolean'],
                ],
            ],

            // Projects (Bidirectional)
            [
                'name' => 'Projects',
                'local_model' => 'Modules\\Projects\\Models\\Project',
                'local_table' => 'projects',
                'odoo_model' => 'project.project',
                'sync_direction' => SyncDirection::BIDIRECTIONAL,
                'priority' => 40,
                'fields' => [
                    ['local' => 'name', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'translatable'],
                    ['local' => 'description', 'odoo' => 'description', 'direction' => 'bidirectional', 'transform' => 'translatable'],
                    ['local' => 'is_active', 'odoo' => 'active', 'direction' => 'bidirectional', 'transform' => 'boolean'],
                    ['local' => 'start_date', 'odoo' => 'date_start', 'direction' => 'bidirectional', 'transform' => 'date'],
                    ['local' => 'allow_timesheets', 'odoo' => 'allow_timesheets', 'direction' => 'bidirectional', 'transform' => 'boolean'],
                ],
            ],

            // Project Tasks (Bidirectional)
            [
                'name' => 'Project Tasks',
                'local_model' => 'Modules\\Projects\\Models\\ProjectTask',
                'local_table' => 'project_tasks',
                'odoo_model' => 'project.task',
                'sync_direction' => SyncDirection::BIDIRECTIONAL,
                'priority' => 41,
                'fields' => [
                    ['local' => 'project_id', 'odoo' => 'project_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => 'Modules\\Projects\\Models\\Project', 'odoo_model' => 'project.project']],
                    ['local' => 'name', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'translatable'],
                    ['local' => 'description', 'odoo' => 'description', 'direction' => 'bidirectional', 'transform' => 'translatable'],
                    ['local' => 'start_date', 'odoo' => 'date_deadline', 'direction' => 'bidirectional', 'transform' => 'date'],
                    ['local' => 'estimated_hours', 'odoo' => 'planned_hours', 'direction' => 'bidirectional', 'transform' => 'direct'],
                ],
            ],

            // Time Entries (Bidirectional)
            [
                'name' => 'Time Entries',
                'local_model' => 'Modules\\Projects\\Models\\ProjectTimeEntry',
                'local_table' => 'project_time_entries',
                'odoo_model' => 'account.analytic.line',
                'sync_direction' => SyncDirection::BIDIRECTIONAL,
                'priority' => 42,
                'fields' => [
                    ['local' => 'task_id', 'odoo' => 'task_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => 'Modules\\Projects\\Models\\ProjectTask', 'odoo_model' => 'project.task']],
                    ['local' => 'user_id', 'odoo' => 'user_id', 'direction' => 'bidirectional', 'transform' => 'relation', 'config' => ['model' => 'Modules\\Auth\\Models\\User', 'odoo_model' => 'res.users']],
                    ['local' => 'hours', 'odoo' => 'unit_amount', 'direction' => 'bidirectional', 'transform' => 'direct'],
                    ['local' => 'description', 'odoo' => 'name', 'direction' => 'bidirectional', 'transform' => 'direct'],
                    ['local' => 'date', 'odoo' => 'date', 'direction' => 'bidirectional', 'transform' => 'date'],
                ],
            ],
        ];
    }
}
