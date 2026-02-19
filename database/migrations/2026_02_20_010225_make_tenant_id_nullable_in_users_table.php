<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL for PostgreSQL to alter column nullability
        \DB::statement('ALTER TABLE users ALTER COLUMN tenant_id DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore NOT NULL constraint (only if all existing records have tenant_id)
        \DB::statement('ALTER TABLE users ALTER COLUMN tenant_id SET NOT NULL');
    }
};
