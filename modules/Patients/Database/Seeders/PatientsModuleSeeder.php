<?php

namespace Modules\Patients\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Tenant;
use Modules\Patients\Models\Patient;
use Modules\Patients\Models\PatientMedicalHistory;

class PatientsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Patients module data...');

        $tenant = Tenant::where('slug', 'system')->first();

        if (!$tenant) {
            $this->command->warn('System tenant not found. Skipping patient seeds.');
            return;
        }

        $this->seedDemoPatients($tenant);

        $this->command->info('Patients module seeded successfully.');
    }

    private function seedDemoPatients(Tenant $tenant): void
    {
        $patients = [
            [
                'first_name' => 'Ahmed',
                'last_name' => 'Mohamed',
                'email' => 'ahmed.mohamed@example.com',
                'phone' => '+201234567001',
                'gender' => 'male',
                'date_of_birth' => '1985-03-15',
                'country' => 'Egypt',
                'city' => 'Cairo',
                'address' => '123 Zamalek Street',
                'emergency_contact_name' => 'Fatma Mohamed',
                'emergency_contact_phone' => '+201234567002',
                'referral_source' => 'google',
                'medical_history' => [
                    'fitzpatrick_type' => 'IV',
                    'blood_type' => 'A+',
                    'allergies' => ['penicillin'],
                    'contraindications' => [],
                ],
            ],
            [
                'first_name' => 'Fatma',
                'last_name' => 'Ali',
                'email' => 'fatma.ali@example.com',
                'phone' => '+201234567003',
                'gender' => 'female',
                'date_of_birth' => '1990-07-22',
                'country' => 'Egypt',
                'city' => 'Alexandria',
                'address' => '456 Corniche Road',
                'referral_source' => 'friend',
                'medical_history' => [
                    'fitzpatrick_type' => 'III',
                    'blood_type' => 'O+',
                    'allergies' => [],
                    'contraindications' => [],
                ],
            ],
            [
                'first_name' => 'Omar',
                'last_name' => 'Hassan',
                'email' => 'omar.hassan@example.com',
                'phone' => '+201234567004',
                'gender' => 'male',
                'date_of_birth' => '1978-11-08',
                'country' => 'Egypt',
                'city' => 'Giza',
                'address' => '789 Pyramids Street',
                'referral_source' => 'social_media',
                'medical_history' => [
                    'fitzpatrick_type' => 'V',
                    'blood_type' => 'B+',
                    'allergies' => ['latex'],
                    'contraindications' => ['diabetes'],
                    'current_medications' => ['Metformin'],
                    'notes' => 'Type 2 Diabetes - well controlled',
                ],
            ],
            [
                'first_name' => 'Nour',
                'last_name' => 'Ibrahim',
                'email' => 'nour.ibrahim@example.com',
                'phone' => '+201234567005',
                'gender' => 'female',
                'date_of_birth' => '1995-02-28',
                'country' => 'Egypt',
                'city' => 'Cairo',
                'address' => '321 Maadi Street',
                'referral_source' => 'instagram',
                'medical_history' => [
                    'fitzpatrick_type' => 'III',
                    'blood_type' => 'AB+',
                    'allergies' => [],
                    'contraindications' => [],
                ],
            ],
            [
                'first_name' => 'Khaled',
                'last_name' => 'Mahmoud',
                'email' => 'khaled.mahmoud@example.com',
                'phone' => '+201234567006',
                'gender' => 'male',
                'date_of_birth' => '1982-09-12',
                'country' => 'Egypt',
                'city' => 'Cairo',
                'address' => '654 Heliopolis Street',
                'referral_source' => 'doctor',
                'referred_by_name' => 'Dr. Samir Ahmed',
                'medical_history' => [
                    'fitzpatrick_type' => 'IV',
                    'blood_type' => 'O-',
                    'allergies' => [],
                    'contraindications' => ['keloid_history'],
                    'notes' => 'History of keloid scarring',
                ],
            ],
        ];

        foreach ($patients as $patientData) {
            $medicalHistoryData = $patientData['medical_history'] ?? [];
            unset($patientData['medical_history']);

            $patientData['tenant_id'] = $tenant->id;
            $patientData['sms_consent'] = true;
            $patientData['email_consent'] = true;
            $patientData['status'] = 'active';

            $patient = Patient::firstOrCreate(
                ['email' => $patientData['email']],
                $patientData
            );

            // Skip medical history creation during seeding to avoid memory issues
            // Medical history can be added via the admin panel

            $this->command->info("  - Patient created: {$patient->full_name}");
        }
    }
}
