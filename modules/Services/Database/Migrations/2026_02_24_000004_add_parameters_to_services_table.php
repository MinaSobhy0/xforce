<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->uuid('parameter_template_id')->nullable()->after('consent_template_id');
            $table->string('parameter_mode', 20)->default('none')->after('parameter_template_id');
            // parameter_mode: 'none', 'template', 'custom'
            $table->boolean('has_dynamic_parameters')->default(false)->after('parameter_mode');

            $table->foreign('parameter_template_id')
                ->references('id')
                ->on('parameter_templates')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['parameter_template_id']);
            $table->dropColumn(['parameter_template_id', 'parameter_mode', 'has_dynamic_parameters']);
        });
    }
};
