<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('parent_id')->nullable()->index();

            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'parent_id', 'sort_order']);
        });

        // Add self-referencing foreign key after table creation
        Schema::table('treatment_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('treatment_categories')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('treatment_categories');
    }
};
