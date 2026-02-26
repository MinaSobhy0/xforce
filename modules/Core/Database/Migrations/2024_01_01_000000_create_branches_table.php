<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();

            $table->string('name', 150);
            $table->string('code', 20)->nullable()->index();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('google_maps_url')->nullable();
            $table->jsonb('working_hours')->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
