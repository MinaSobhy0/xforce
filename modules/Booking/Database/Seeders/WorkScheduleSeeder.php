<?php

namespace Modules\Booking\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Booking\Models\WorkSchedule;
use Modules\Core\Database\Seeders\Concerns\ResolveTenantId;

class WorkScheduleSeeder extends Seeder
{
    use ResolveTenantId;

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();

        $schedules = [
            // Standard Full-Time (Sunday-Thursday, Friday off)
            [
                'code' => 'FT-STD',
                'name' => 'Full Time - Standard',
                'description' => 'Standard full-time schedule (Sun-Thu, 9AM-5PM)',
                'schedule_type' => WorkSchedule::TYPE_FIXED,
                'weekly_hours' => [
                    0 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '17:00', 'break_start' => '13:00', 'break_end' => '14:00'], // Sunday
                    1 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '17:00', 'break_start' => '13:00', 'break_end' => '14:00'], // Monday
                    2 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '17:00', 'break_start' => '13:00', 'break_end' => '14:00'], // Tuesday
                    3 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '17:00', 'break_start' => '13:00', 'break_end' => '14:00'], // Wednesday
                    4 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '17:00', 'break_start' => '13:00', 'break_end' => '14:00'], // Thursday
                    5 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null], // Friday
                    6 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null], // Saturday
                ],
                'slot_duration' => 30,
                'buffer_time' => 10,
                'color' => '#3B82F6',
                'sort_order' => 1,
            ],

            // Morning Shift
            [
                'code' => 'PT-MORN',
                'name' => 'Part Time - Morning',
                'description' => 'Morning shift (Sun-Thu, 9AM-2PM)',
                'schedule_type' => WorkSchedule::TYPE_FIXED,
                'weekly_hours' => [
                    0 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '14:00', 'break_start' => null, 'break_end' => null],
                    1 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '14:00', 'break_start' => null, 'break_end' => null],
                    2 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '14:00', 'break_start' => null, 'break_end' => null],
                    3 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '14:00', 'break_start' => null, 'break_end' => null],
                    4 => ['is_working' => true, 'start_time' => '09:00', 'end_time' => '14:00', 'break_start' => null, 'break_end' => null],
                    5 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                    6 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                ],
                'slot_duration' => 30,
                'buffer_time' => 10,
                'color' => '#F59E0B',
                'sort_order' => 2,
            ],

            // Evening Shift
            [
                'code' => 'PT-EVE',
                'name' => 'Part Time - Evening',
                'description' => 'Evening shift (Sun-Thu, 2PM-9PM)',
                'schedule_type' => WorkSchedule::TYPE_FIXED,
                'weekly_hours' => [
                    0 => ['is_working' => true, 'start_time' => '14:00', 'end_time' => '21:00', 'break_start' => null, 'break_end' => null],
                    1 => ['is_working' => true, 'start_time' => '14:00', 'end_time' => '21:00', 'break_start' => null, 'break_end' => null],
                    2 => ['is_working' => true, 'start_time' => '14:00', 'end_time' => '21:00', 'break_start' => null, 'break_end' => null],
                    3 => ['is_working' => true, 'start_time' => '14:00', 'end_time' => '21:00', 'break_start' => null, 'break_end' => null],
                    4 => ['is_working' => true, 'start_time' => '14:00', 'end_time' => '21:00', 'break_start' => null, 'break_end' => null],
                    5 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                    6 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                ],
                'slot_duration' => 30,
                'buffer_time' => 10,
                'color' => '#8B5CF6',
                'sort_order' => 3,
            ],

            // Weekend Only
            [
                'code' => 'PT-WKND',
                'name' => 'Weekend Only',
                'description' => 'Weekend schedule (Fri-Sat, 10AM-6PM)',
                'schedule_type' => WorkSchedule::TYPE_FIXED,
                'weekly_hours' => [
                    0 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                    1 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                    2 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                    3 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                    4 => ['is_working' => false, 'start_time' => null, 'end_time' => null, 'break_start' => null, 'break_end' => null],
                    5 => ['is_working' => true, 'start_time' => '10:00', 'end_time' => '18:00', 'break_start' => '13:00', 'break_end' => '14:00'],
                    6 => ['is_working' => true, 'start_time' => '10:00', 'end_time' => '18:00', 'break_start' => '13:00', 'break_end' => '14:00'],
                ],
                'slot_duration' => 30,
                'buffer_time' => 10,
                'color' => '#EC4899',
                'sort_order' => 4,
            ],

            // Flexible Schedule
            [
                'code' => 'FLEX-40',
                'name' => 'Flexible - 40 Hours',
                'description' => 'Flexible 40-hour work week (choose your hours)',
                'schedule_type' => WorkSchedule::TYPE_FLEXIBLE,
                'working_days' => [0, 1, 2, 3, 4], // Sun-Thu
                'required_hours_per_week' => 40,
                'flexible_start_time' => '08:00',
                'flexible_end_time' => '20:00',
                'slot_duration' => 30,
                'buffer_time' => 10,
                'color' => '#10B981',
                'sort_order' => 5,
            ],
        ];

        foreach ($schedules as $schedule) {
            $existing = WorkSchedule::where('code', $schedule['code'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$existing) {
                WorkSchedule::create(array_merge($schedule, [
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                ]));
            }
        }
    }
}
