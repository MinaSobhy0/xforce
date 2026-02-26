<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Get all tenants
        $tenants = DB::connection('pgsql')
            ->table('tenants')
            ->where('status', 'active')
            ->get();

        foreach ($tenants as $tenant) {
            $schema = 'tenant_' . str_replace('-', '_', $tenant->slug);

            // Check if schema exists
            $schemaExists = DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$schema]
            );

            if (empty($schemaExists)) {
                continue;
            }

            // Set search path to tenant schema
            DB::statement("SET search_path TO {$schema}");

            // Check if gift_card journal already exists
            $exists = DB::table('journals')
                ->where('type', 'gift_card')
                ->exists();

            if (!$exists) {
                // Get the gift card liability account for default_debit_account_id
                $liabilityAccount = DB::table('chart_of_accounts')
                    ->whereIn('code', ['2220', '2200', '2100'])
                    ->where('is_active', true)
                    ->orderByRaw("CASE code WHEN '2220' THEN 0 WHEN '2200' THEN 1 ELSE 2 END")
                    ->first();

                DB::table('journals')->insert([
                    'id' => Str::orderedUuid(),
                    'tenant_id' => $tenant->id,
                    'code' => 'GC',
                    'name' => json_encode(['en' => 'Gift Card', 'ar' => 'بطاقة هدية']),
                    'type' => 'gift_card',
                    'default_debit_account_id' => $liabilityAccount?->id,
                    'default_credit_account_id' => null,
                    'sequence_prefix' => 'GC',
                    'next_sequence' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Reset search path
            DB::statement("SET search_path TO public");
        }
    }

    public function down(): void
    {
        $tenants = DB::connection('pgsql')
            ->table('tenants')
            ->get();

        foreach ($tenants as $tenant) {
            $schema = 'tenant_' . str_replace('-', '_', $tenant->slug);

            $schemaExists = DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$schema]
            );

            if (empty($schemaExists)) {
                continue;
            }

            DB::statement("SET search_path TO {$schema}");

            DB::table('journals')
                ->where('type', 'gift_card')
                ->delete();

            DB::statement("SET search_path TO public");
        }
    }
};
