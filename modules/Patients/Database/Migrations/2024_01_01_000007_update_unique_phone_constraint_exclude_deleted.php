<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old unique constraint
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_tenant_phone_unique');
        });

        // Create partial unique index that excludes soft-deleted records (PostgreSQL)
        DB::statement('CREATE UNIQUE INDEX patients_tenant_phone_unique ON patients (tenant_id, phone) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial unique index
        DB::statement('DROP INDEX IF EXISTS patients_tenant_phone_unique');

        // Restore the old unique constraint
        Schema::table('patients', function (Blueprint $table) {
            $table->unique(['tenant_id', 'phone'], 'patients_tenant_phone_unique');
        });
    }
};
