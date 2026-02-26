<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code')->index();
            $table->jsonb('name');
            $table->uuid('parent_id')->nullable()->index();
            $table->string('type')->index();
            $table->string('sub_type')->nullable();
            $table->boolean('is_system')->default(false);
            $table->integer('balance_minor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
