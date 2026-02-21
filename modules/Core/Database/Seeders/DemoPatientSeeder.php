<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Patients\Models\Patient;
use Carbon\Carbon;

class DemoPatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patients = [
            [
                'code' => 'PAT-0001',
                'name' => 'Sarah Ahmed',
                'phone' => '+201001234567',
                'email' => 'sarah.ahmed@example.com',
                'gender' => 'female',
                'date_of_birth' => '1990-05-15',
                'skin_type' => 3,
                'is_vip' => true,
            ],
            [
                'code' => 'PAT-0002',
                'name' => 'Nour Hassan',
                'phone' => '+201002345678',
                'email' => 'nour.hassan@example.com',
                'gender' => 'female',
                'date_of_birth' => '1988-08-22',
                'skin_type' => 4,
                'is_vip' => false,
            ],
            [
                'code' => 'PAT-0003',
                'name' => 'Mona Ibrahim',
                'phone' => '+201003456789',
                'email' => 'mona.ibrahim@example.com',
                'gender' => 'female',
                'date_of_birth' => '1995-03-10',
                'skin_type' => 2,
                'is_vip' => false,
            ],
            [
                'code' => 'PAT-0004',
                'name' => 'Layla Mahmoud',
                'phone' => '+201004567890',
                'email' => 'layla.mahmoud@example.com',
                'gender' => 'female',
                'date_of_birth' => '1992-11-28',
                'skin_type' => 3,
                'is_vip' => true,
            ],
            [
                'code' => 'PAT-0005',
                'name' => 'Fatma Ali',
                'phone' => '+201005678901',
                'email' => 'fatma.ali@example.com',
                'gender' => 'female',
                'date_of_birth' => '1985-07-03',
                'skin_type' => 5,
                'is_vip' => false,
            ],
            [
                'code' => 'PAT-0006',
                'name' => 'Ahmed Mohamed',
                'phone' => '+201006789012',
                'email' => 'ahmed.mohamed@example.com',
                'gender' => 'male',
                'date_of_birth' => '1982-01-20',
                'skin_type' => 4,
                'is_vip' => false,
            ],
            [
                'code' => 'PAT-0007',
                'name' => 'Yasmin Kamel',
                'phone' => '+201007890123',
                'email' => 'yasmin.kamel@example.com',
                'gender' => 'female',
                'date_of_birth' => '1998-09-14',
                'skin_type' => 2,
                'is_vip' => false,
            ],
            [
                'code' => 'PAT-0008',
                'name' => 'Heba Saleh',
                'phone' => '+201008901234',
                'email' => 'heba.saleh@example.com',
                'gender' => 'female',
                'date_of_birth' => '1993-04-07',
                'skin_type' => 3,
                'is_vip' => true,
            ],
            [
                'code' => 'PAT-0009',
                'name' => 'Dina Fawzy',
                'phone' => '+201009012345',
                'email' => 'dina.fawzy@example.com',
                'gender' => 'female',
                'date_of_birth' => '1991-12-25',
                'skin_type' => 4,
                'is_vip' => false,
            ],
            [
                'code' => 'PAT-0010',
                'name' => 'Rania Youssef',
                'phone' => '+201010123456',
                'email' => 'rania.youssef@example.com',
                'gender' => 'female',
                'date_of_birth' => '1987-06-18',
                'skin_type' => 3,
                'is_vip' => false,
            ],
        ];

        foreach ($patients as $patientData) {
            Patient::firstOrCreate(
                ['code' => $patientData['code']],
                array_merge($patientData, [
                    'is_active' => true,
                    'source' => 'walk_in',
                    'accepts_marketing' => true,
                    'preferred_language' => 'ar',
                ])
            );
        }

        $this->command->info('Demo patients seeded.');
    }
}
