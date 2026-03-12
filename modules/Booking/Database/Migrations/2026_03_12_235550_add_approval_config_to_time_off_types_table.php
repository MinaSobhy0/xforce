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
        Schema::table('time_off_types', function (Blueprint $table) {
            // Approval configuration
            $table->string('approval_type')->default('any')->after('requires_approval');
            $table->jsonb('approval_role_ids')->nullable()->after('approval_type');
            $table->jsonb('approval_user_ids')->nullable()->after('approval_role_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_off_types', function (Blueprint $table) {
            $table->dropColumn(['approval_type', 'approval_role_ids', 'approval_user_ids']);
        });
    }
};
