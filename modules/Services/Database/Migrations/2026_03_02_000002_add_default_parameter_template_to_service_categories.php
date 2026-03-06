<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->foreignId('default_parameter_template_id')
                ->nullable()
                ->after('is_active')
                ->constrained('parameter_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_parameter_template_id');
        });
    }
};
