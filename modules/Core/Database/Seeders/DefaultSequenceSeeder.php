<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Sequence;
use XLinic\Framework\Core\Tenancy\TenantManager;

class DefaultSequenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates default sequences for the tenant.
     */
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);
        $tenant = $tenantManager->current();

        $tenantId = $tenant?->id;

        // Define default sequences
        $sequences = [
            // Patient sequence: PAT-000001
            'patient' => [
                'prefix' => 'PAT',
                'format' => '{prefix}-{number:6}',
                'start_at' => 0,
            ],
            // Invoice sequence: INV-2024-000001
            'invoice' => [
                'prefix' => 'INV',
                'format' => '{prefix}-{year}-{number:6}',
                'start_at' => 0,
            ],
            // Appointment sequence: APT-000001
            'appointment' => [
                'prefix' => 'APT',
                'format' => '{prefix}-{number:6}',
                'start_at' => 0,
            ],
            // Gift Card sequence: GC-000001
            'gift_card' => [
                'prefix' => 'GC',
                'format' => '{prefix}-{number:6}',
                'start_at' => 0,
            ],
            // Journal Entry sequence: JE-2024-000001
            'journal_entry' => [
                'prefix' => 'JE',
                'format' => '{prefix}-{year}-{number:6}',
                'start_at' => 0,
            ],
            // Purchase Order sequence: PO-000001
            'purchase_order' => [
                'prefix' => 'PO',
                'format' => '{prefix}-{number:6}',
                'start_at' => 0,
            ],
            // Payment sequence: PAY-000001
            'payment' => [
                'prefix' => 'PAY',
                'format' => '{prefix}-{number:6}',
                'start_at' => 0,
            ],
            // Treatment sequence: TRT-000001
            'treatment' => [
                'prefix' => 'TRT',
                'format' => '{prefix}-{number:6}',
                'start_at' => 0,
            ],
            // Equipment sequence: EQP-000001
            'equipment' => [
                'prefix' => 'EQP',
                'format' => '{prefix}-{number:6}',
                'start_at' => 0,
            ],
            // Branch sequence: BR-001
            'branch' => [
                'prefix' => 'BR',
                'format' => '{prefix}-{number:3}',
                'start_at' => 0,
            ],
            // Visit sequence: VST-000001
            'visit' => [
                'prefix' => 'VST',
                'format' => '{prefix}-{number:6}',
                'start_at' => 0,
            ],
        ];

        // Seed the sequences
        Sequence::seed($sequences, $tenantId);

        $this->command?->info('Default sequences created successfully: ' . implode(', ', array_keys($sequences)));
    }
}
