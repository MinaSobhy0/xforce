<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('staff_profile_id');
            $table->foreignId('service_id')->nullable();
            $table->foreignId('service_category_id')->nullable();
            $table->string('commission_type', 20)->default('percentage');
            $table->integer('flat_amount_minor')->default(0);
            $table->decimal('percentage', 5, 2)->default(10.00);
            $table->integer('tier_from_minor')->nullable();
            $table->integer('tier_to_minor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->onDelete('cascade');

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');

            $table->foreign('service_category_id')
                ->references('id')
                ->on('service_categories')
                ->onDelete('cascade');

            $table->index(['tenant_id', 'staff_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_commissions');
    }
};
