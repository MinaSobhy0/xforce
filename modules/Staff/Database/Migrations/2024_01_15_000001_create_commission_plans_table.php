<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('commission_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('commission_type')->default('percentage'); // percentage, flat, tiered
            $table->decimal('default_percentage', 5, 2)->nullable();
            $table->integer('default_flat_amount_minor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('commission_plan_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('commission_plan_id');
            $table->foreignId('service_id')->nullable();
            $table->foreignId('service_category_id')->nullable();
            $table->string('commission_type')->default('percentage');
            $table->decimal('percentage', 5, 2)->nullable();
            $table->integer('flat_amount_minor')->default(0);
            $table->integer('tier_from_minor')->nullable();
            $table->integer('tier_to_minor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('commission_plan_id')
                ->references('id')
                ->on('commission_plans')
                ->onDelete('cascade');

            $table->index(['commission_plan_id', 'service_id']);
            $table->index(['commission_plan_id', 'service_category_id']);
        });

        // Add commission_plan_id to staff_profiles
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->foreignId('commission_plan_id')->nullable()->after('branch_id');

            $table->foreign('commission_plan_id')
                ->references('id')
                ->on('commission_plans')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropForeign(['commission_plan_id']);
            $table->dropColumn('commission_plan_id');
        });

        Schema::dropIfExists('commission_plan_rules');
        Schema::dropIfExists('commission_plans');
    }
};
