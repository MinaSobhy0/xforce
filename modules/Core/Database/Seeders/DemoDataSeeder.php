<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding demo data...');

        // Seed in order of dependencies
        $this->call([
            DemoBranchSeeder::class,
            DemoTreatmentSeeder::class,
            DemoPatientSeeder::class,
            DemoAppointmentSeeder::class,
            DemoInvoiceSeeder::class,
        ]);

        $this->command->info('Demo data seeded successfully!');
    }
}
