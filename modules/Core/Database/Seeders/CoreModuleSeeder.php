<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantStatus;
use Modules\Auth\Models\User;
use Modules\Auth\Models\Role;

class CoreModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Core module data...');

        $this->seedSystemTenant();
        $this->seedDemoTenants();

        $this->command->info('Core module seeded successfully.');
    }

    private function seedSystemTenant(): void
    {
        $systemTenant = Tenant::firstOrCreate(
            ['slug' => 'system'],
            [
                'name' => 'System Tenant',
                'slug' => 'system',
                'status' => TenantStatus::ACTIVE,
                'contact_name' => 'System Administrator',
                'contact_email' => 'admin@xlinic.com',
                'contact_phone' => '+20123456789',
                'address' => 'System Address',
                'city' => 'Cairo',
                'country' => 'EG',
                'postal_code' => '12345',
                'max_users' => 1000,
                'max_patients' => 100000,
                'max_storage_mb' => 50000,
                'features' => [
                    'users', 'patients', 'appointments', 'treatments',
                    'inventory', 'billing', 'reports', 'marketing',
                    'portal', 'api'
                ],
                'timezone' => 'Africa/Cairo',
                'locale' => 'en',
                'currency' => 'EGP',
                'tax_rate' => 14.00,
                'primary_color' => '#3B82F6',
                'secondary_color' => '#EF4444',
            ]
        );

        $this->command->info("✓ System tenant created: {$systemTenant->name}");
    }

    private function seedDemoTenants(): void
    {
        $demoTenants = [
            [
                'name' => 'Cairo Laser Center',
                'slug' => 'cairo-laser-center',
                'contact_name' => 'Dr. Ahmed Mohamed',
                'contact_email' => 'ahmed@cairo-laser.com',
                'contact_phone' => '+201234567890',
                'city' => 'Cairo',
                'country' => 'EG',
                'timezone' => 'Africa/Cairo',
                'features' => ['users', 'patients', 'appointments', 'treatments', 'billing'],
            ],
            [
                'name' => 'Alexandria Beauty Clinic',
                'slug' => 'alexandria-beauty-clinic',
                'contact_name' => 'Dr. Fatma Ali',
                'contact_email' => 'fatma@alex-beauty.com',
                'contact_phone' => '+201987654321',
                'city' => 'Alexandria',
                'country' => 'EG',
                'timezone' => 'Africa/Cairo',
                'features' => ['users', 'patients', 'appointments', 'treatments'],
            ],
            [
                'name' => 'Riyadh Aesthetic Center',
                'slug' => 'riyadh-aesthetic-center',
                'contact_name' => 'Dr. Omar Al-Rashid',
                'contact_email' => 'omar@riyadh-aesthetic.com',
                'contact_phone' => '+966501234567',
                'city' => 'Riyadh',
                'country' => 'SA',
                'timezone' => 'Asia/Riyadh',
                'currency' => 'SAR',
                'features' => ['users', 'patients', 'appointments', 'treatments', 'billing', 'reports'],
            ],
            [
                'name' => 'Dubai Laser Spa',
                'slug' => 'dubai-laser-spa',
                'contact_name' => 'Dr. Sarah Hassan',
                'contact_email' => 'sarah@dubai-laser-spa.com',
                'contact_phone' => '+971501234567',
                'city' => 'Dubai',
                'country' => 'AE',
                'timezone' => 'Asia/Dubai',
                'currency' => 'AED',
                'features' => ['users', 'patients', 'appointments', 'treatments', 'inventory', 'billing'],
            ],
        ];

        foreach ($demoTenants as $tenantData) {
            $tenantData = array_merge([
                'status' => TenantStatus::ACTIVE,
                'address' => 'Demo Address',
                'postal_code' => '12345',
                'max_users' => 50,
                'max_patients' => 5000,
                'max_storage_mb' => 2048,
                'locale' => 'en',
                'currency' => 'EGP',
                'tax_rate' => 14.00,
                'subscription_expires_at' => now()->addYear(),
            ], $tenantData);

            $tenant = Tenant::firstOrCreate(
                ['slug' => $tenantData['slug']],
                $tenantData
            );

            $this->command->info("✓ Demo tenant created: {$tenant->name}");
        }
    }
}