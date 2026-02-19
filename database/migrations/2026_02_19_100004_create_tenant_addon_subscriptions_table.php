<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_addon_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('module_code', 50);
            $table->integer('price_minor')->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->string('status', 20)->default('active'); // active, cancelled, pending
            $table->timestamp('started_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('module_code')->references('code')->on('modules')->onDelete('cascade');

            $table->unique(['tenant_id', 'module_code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['module_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_addon_subscriptions');
    }
};
