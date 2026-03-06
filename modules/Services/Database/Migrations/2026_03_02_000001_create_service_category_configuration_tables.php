<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Service Category Equipment pivot table
        Schema::create('service_category_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('service_category_id');
            $table->foreignId('equipment_id');
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->foreign('service_category_id')
                ->references('id')
                ->on('service_categories')
                ->cascadeOnDelete();
            $table->foreign('equipment_id')
                ->references('id')
                ->on('equipment')
                ->cascadeOnDelete();
            $table->unique(['service_category_id', 'equipment_id'], 'svc_cat_equip_unique');
        });

        // Service Category Qualified Staff pivot table
        Schema::create('service_category_qualified_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('service_category_id');
            $table->foreignId('staff_profile_id');
            $table->timestamps();

            $table->foreign('service_category_id')
                ->references('id')
                ->on('service_categories')
                ->cascadeOnDelete();
            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->cascadeOnDelete();
            $table->unique(['service_category_id', 'staff_profile_id'], 'svc_cat_staff_unique');
        });

        // Service Category Rooms pivot table
        Schema::create('service_category_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('service_category_id');
            $table->foreignId('room_id');
            $table->boolean('is_primary')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->foreign('service_category_id')
                ->references('id')
                ->on('service_categories')
                ->cascadeOnDelete();
            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->cascadeOnDelete();
            $table->unique(['service_category_id', 'room_id'], 'svc_cat_room_unique');
        });

        // Service Category Consumables pivot table
        Schema::create('service_category_consumables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('service_category_id');
            $table->foreignId('product_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->foreign('service_category_id')
                ->references('id')
                ->on('service_categories')
                ->cascadeOnDelete();
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
            $table->unique(['service_category_id', 'product_id'], 'svc_cat_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_category_consumables');
        Schema::dropIfExists('service_category_rooms');
        Schema::dropIfExists('service_category_qualified_staff');
        Schema::dropIfExists('service_category_equipment');
    }
};
