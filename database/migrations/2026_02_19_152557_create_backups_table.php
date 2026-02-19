<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type')->default('full'); // full, database, files, tenant
            $table->string('disk')->default('local'); // local, s3
            $table->string('path')->nullable();
            $table->string('filename');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->uuid('tenant_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index('tenant_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
