<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Booking\Models\Appointment;
use Modules\Patients\Models\Patient;
use Modules\Treatments\Models\Treatment;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;
use Modules\Auth\Models\User;
use Carbon\Carbon;

class DemoAppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patients = Patient::all();
        $treatments = Treatment::where('is_active', true)->get();
        $branch = Branch::where('is_headquarters', true)->first();
        $room = Room::where('branch_id', $branch?->id)->where('type', 'treatment')->first();
        $practitioner = User::first();

        if ($patients->isEmpty() || $treatments->isEmpty() || !$branch || !$practitioner) {
            $this->command->warn('Missing required data for appointments. Skipping...');
            return;
        }

        $statuses = [
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
        ];

        $appointmentCount = 0;

        // Create appointments for the past 30 days
        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $date = now()->subDays($daysAgo);

            // Skip Fridays
            if ($date->isFriday()) {
                continue;
            }

            // 2-5 appointments per day
            $appointmentsPerDay = rand(2, 5);

            for ($i = 0; $i < $appointmentsPerDay; $i++) {
                $patient = $patients->random();
                $treatment = $treatments->random();
                $startHour = rand(9, 18);
                $startTime = sprintf('%02d:00', $startHour);

                $status = $daysAgo > 0
                    ? Appointment::STATUS_COMPLETED
                    : $statuses[array_rand($statuses)];

                $code = sprintf('APT-%06d', ++$appointmentCount);

                Appointment::firstOrCreate(
                    ['code' => $code],
                    [
                        'patient_id' => $patient->id,
                        'treatment_id' => $treatment->id,
                        'branch_id' => $branch->id,
                        'room_id' => $room?->id,
                        'practitioner_user_id' => $practitioner->id,
                        'date' => $date->toDateString(),
                        'start_time' => $startTime,
                        'end_time' => Carbon::parse($startTime)->addMinutes($treatment->duration_minutes)->format('H:i'),
                        'duration_minutes' => $treatment->duration_minutes,
                        'status' => $status,
                        'price_minor' => $treatment->price_minor,
                        'discount_minor' => rand(0, 1) ? rand(5000, 20000) : 0,
                        'source' => ['walk_in', 'phone', 'website'][array_rand(['walk_in', 'phone', 'website'])],
                        'confirmed_at' => in_array($status, [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])
                            ? $date->copy()->setHour(rand(8, 10))
                            : null,
                        'completed_at' => $status === Appointment::STATUS_COMPLETED
                            ? $date->copy()->setHour($startHour + 1)
                            : null,
                    ]
                );
            }
        }

        // Create some future appointments
        for ($daysAhead = 1; $daysAhead <= 14; $daysAhead++) {
            $date = now()->addDays($daysAhead);

            if ($date->isFriday()) {
                continue;
            }

            $appointmentsPerDay = rand(1, 3);

            for ($i = 0; $i < $appointmentsPerDay; $i++) {
                $patient = $patients->random();
                $treatment = $treatments->random();
                $startHour = rand(10, 17);
                $startTime = sprintf('%02d:00', $startHour);

                $code = sprintf('APT-%06d', ++$appointmentCount);

                Appointment::firstOrCreate(
                    ['code' => $code],
                    [
                        'patient_id' => $patient->id,
                        'treatment_id' => $treatment->id,
                        'branch_id' => $branch->id,
                        'room_id' => $room?->id,
                        'practitioner_user_id' => $practitioner->id,
                        'date' => $date->toDateString(),
                        'start_time' => $startTime,
                        'end_time' => Carbon::parse($startTime)->addMinutes($treatment->duration_minutes)->format('H:i'),
                        'duration_minutes' => $treatment->duration_minutes,
                        'status' => rand(0, 1) ? Appointment::STATUS_CONFIRMED : Appointment::STATUS_SCHEDULED,
                        'price_minor' => $treatment->price_minor,
                        'discount_minor' => 0,
                        'source' => 'phone',
                    ]
                );
            }
        }

        $this->command->info("Demo appointments seeded ({$appointmentCount} total).");
    }
}
