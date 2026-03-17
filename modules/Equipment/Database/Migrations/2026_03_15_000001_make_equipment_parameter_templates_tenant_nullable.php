<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make tenant_id nullable to allow system templates (tenant_id = null)
        DB::statement('ALTER TABLE equipment_parameter_templates ALTER COLUMN tenant_id DROP NOT NULL');
    }

    public function down(): void
    {
        // First delete any records with null tenant_id
        DB::table('equipment_parameter_templates')->whereNull('tenant_id')->delete();

        // Then make it NOT NULL again
        DB::statement('ALTER TABLE equipment_parameter_templates ALTER COLUMN tenant_id SET NOT NULL');
    }
};
