<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_products', function (Blueprint $table) {
            $table->foreignId('visit_id')->nullable()->after('session_data_id')->constrained('visits')->nullOnDelete();
            $table->index('visit_id');
        });
    }

    public function down(): void
    {
        Schema::table('session_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visit_id');
        });
    }
};
