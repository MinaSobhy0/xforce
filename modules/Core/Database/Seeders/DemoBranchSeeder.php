<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;

class DemoBranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Main Branch
        $mainBranch = Branch::firstOrCreate(
            ['slug' => 'main-branch'],
            [
                'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
                'address' => '123 Main Street, Downtown',
                'phone' => '+201234567890',
                'email' => 'main@clinic.com',
                'google_maps_url' => 'https://maps.google.com/?q=30.0444,31.2357',
                'is_headquarters' => true,
                'is_active' => true,
                'timezone' => 'Africa/Cairo',
                'currency_code' => 'EGP',
                'working_hours' => [
                    'sunday' => ['open' => '09:00', 'close' => '21:00'],
                    'monday' => ['open' => '09:00', 'close' => '21:00'],
                    'tuesday' => ['open' => '09:00', 'close' => '21:00'],
                    'wednesday' => ['open' => '09:00', 'close' => '21:00'],
                    'thursday' => ['open' => '09:00', 'close' => '21:00'],
                    'friday' => null,
                    'saturday' => ['open' => '10:00', 'close' => '18:00'],
                ],
            ]
        );

        // Create rooms for main branch
        $rooms = [
            ['name' => ['en' => 'Laser Room 1', 'ar' => 'غرفة الليزر 1'], 'type' => 'treatment', 'capacity' => 1],
            ['name' => ['en' => 'Laser Room 2', 'ar' => 'غرفة الليزر 2'], 'type' => 'treatment', 'capacity' => 1],
            ['name' => ['en' => 'Consultation Room', 'ar' => 'غرفة الاستشارة'], 'type' => 'consultation', 'capacity' => 2],
            ['name' => ['en' => 'Waiting Area', 'ar' => 'منطقة الانتظار'], 'type' => 'waiting', 'capacity' => 10],
        ];

        foreach ($rooms as $index => $roomData) {
            Room::firstOrCreate(
                [
                    'branch_id' => $mainBranch->id,
                    'name->en' => $roomData['name']['en'],
                ],
                [
                    'name' => $roomData['name'],
                    'type' => $roomData['type'],
                    'capacity' => $roomData['capacity'],
                    'is_active' => true,
                    'is_bookable' => $roomData['type'] === 'treatment',
                    'sort_order' => $index + 1,
                ]
            );
        }

        // Second Branch
        $secondBranch = Branch::firstOrCreate(
            ['slug' => 'second-branch'],
            [
                'name' => ['en' => 'Maadi Branch', 'ar' => 'فرع المعادي'],
                'address' => '45 Road 9, Maadi',
                'phone' => '+201234567891',
                'email' => 'maadi@clinic.com',
                'is_headquarters' => false,
                'is_active' => true,
                'timezone' => 'Africa/Cairo',
                'currency_code' => 'EGP',
                'working_hours' => [
                    'sunday' => ['open' => '10:00', 'close' => '20:00'],
                    'monday' => ['open' => '10:00', 'close' => '20:00'],
                    'tuesday' => ['open' => '10:00', 'close' => '20:00'],
                    'wednesday' => ['open' => '10:00', 'close' => '20:00'],
                    'thursday' => ['open' => '10:00', 'close' => '20:00'],
                    'friday' => null,
                    'saturday' => ['open' => '10:00', 'close' => '16:00'],
                ],
            ]
        );

        // Create rooms for second branch
        Room::firstOrCreate(
            [
                'branch_id' => $secondBranch->id,
                'name->en' => 'Laser Room',
            ],
            [
                'name' => ['en' => 'Laser Room', 'ar' => 'غرفة الليزر'],
                'type' => 'treatment',
                'capacity' => 1,
                'is_active' => true,
                'is_bookable' => true,
                'sort_order' => 1,
            ]
        );

        $this->command->info('Branches and rooms seeded.');
    }
}
