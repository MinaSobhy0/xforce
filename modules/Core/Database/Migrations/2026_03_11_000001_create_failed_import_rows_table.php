<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('failed_import_rows')) {
            return;
        }

        Schema::create('failed_import_rows', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->foreignId('import_id');
            $table->text('validation_error')->nullable();
            $table->timestamps();

            $table->foreign('import_id')
                ->references('id')
                ->on('imports')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_import_rows');
    }
};
