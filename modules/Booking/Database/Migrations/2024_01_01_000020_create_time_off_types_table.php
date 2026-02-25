<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_off_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 50)->index();
            $table->text('description')->nullable();
            $table->string('color', 20)->default('gray');
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->integer('default_days_per_year')->default(0);
            $table->integer('max_days_per_request')->nullable();
            $table->integer('min_days_notice')->default(0);
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('allow_partial_day')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off_types');
    }
};
