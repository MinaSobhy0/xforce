<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Get all tenant schemas directly from information_schema
        $schemas = DB::select(
            "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'tenant%'"
        );

        foreach ($schemas as $schemaRow) {
            $schema = $schemaRow->schema_name;

            // Set search path to tenant schema (quote if contains hyphen)
            $quotedSchema = strpos($schema, '-') !== false ? "\"{$schema}\"" : $schema;
            DB::statement("SET search_path TO {$quotedSchema}");

            // Check if journals table exists in this schema
            $tableExists = DB::select(
                "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name = 'journals'",
                [$schema]
            );

            if (empty($tableExists)) {
                DB::statement("SET search_path TO public");
                continue;
            }

            // Check if gift_card journal already exists
            $exists = DB::table('journals')
                ->where('type', 'gift_card')
                ->exists();

            if (!$exists) {
                // Get tenant_id from an existing journal record
                $existingJournal = DB::table('journals')->first();
                $tenantId = $existingJournal?->tenant_id;

                if (!$tenantId) {
                    DB::statement("SET search_path TO public");
                    continue;
                }

                // Get the gift card liability account for default_debit_account_id
                $liabilityAccount = DB::table('chart_of_accounts')
                    ->whereIn('code', ['2220', '2200', '2100'])
                    ->where('is_active', true)
                    ->orderByRaw("CASE code WHEN '2220' THEN 0 WHEN '2200' THEN 1 ELSE 2 END")
                    ->first();

                DB::table('journals')->insert([
                    'id' => Str::orderedUuid(),
                    'tenant_id' => $tenantId,
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
        // Get all tenant schemas directly from information_schema
        $schemas = DB::select(
            "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'tenant%'"
        );

        foreach ($schemas as $schemaRow) {
            $schema = $schemaRow->schema_name;
            $quotedSchema = strpos($schema, '-') !== false ? "\"{$schema}\"" : $schema;

            DB::statement("SET search_path TO {$quotedSchema}");

            // Check if journals table exists
            $tableExists = DB::select(
                "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name = 'journals'",
                [$schema]
            );

            if (!empty($tableExists)) {
                DB::table('journals')
                    ->where('type', 'gift_card')
                    ->delete();
            }

            DB::statement("SET search_path TO public");
        }
    }
};
