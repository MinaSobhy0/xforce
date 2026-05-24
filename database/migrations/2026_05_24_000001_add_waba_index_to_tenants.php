<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partial expression index on the JSONB path Meta webhooks route by.
 *
 * Reverse lookup: Tenant::findByWabaId($wabaId) does
 *   WHERE settings->'messaging'->'whatsapp'->'meta'->>'waba_id' = ?
 * which is what Eloquent's arrow operator emits. Without this index the
 * webhook handler would seq-scan public.tenants on every Meta delivery.
 *
 * Partial: most tenants haven't connected WhatsApp yet — no need to index
 * NULL rows. The predicate keeps the index small and the partial condition
 * lets Postgres use it for the equality query the resolver issues.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('central')->statement(
            "CREATE INDEX IF NOT EXISTS tenants_waba_id_idx
             ON public.tenants ((settings->'messaging'->'whatsapp'->'meta'->>'waba_id'))
             WHERE settings->'messaging'->'whatsapp'->'meta'->>'waba_id' IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::connection('central')->statement('DROP INDEX IF EXISTS public.tenants_waba_id_idx');
    }
};
