<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->uuid('user_id');
            $table->string('resource_class');
            $table->string('name');
            $table->json('column_map');
            $table->json('options')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'resource_class']);
            $table->index(['user_id', 'resource_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mappings');
    }
};
