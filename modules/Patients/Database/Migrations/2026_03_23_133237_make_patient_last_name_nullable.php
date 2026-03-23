<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('last_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First set empty values to a placeholder
        DB::table('patients')
            ->whereNull('last_name')
            ->orWhere('last_name', '')
            ->update(['last_name' => '-']);

        Schema::table('patients', function (Blueprint $table) {
            $table->string('last_name')->nullable(false)->change();
        });
    }
};
