<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_off_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('user_id')->index();
            $table->uuid('time_off_type_id')->index();
            $table->integer('year');
            $table->decimal('allocated_days', 5, 1)->default(0);
            $table->decimal('used_days', 5, 1)->default(0);
            $table->decimal('carried_over_days', 5, 1)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('time_off_type_id')->references('id')->on('time_off_types')->onDelete('cascade');

            $table->unique(['tenant_id', 'user_id', 'time_off_type_id', 'year'], 'time_off_allocation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off_allocations');
    }
};
