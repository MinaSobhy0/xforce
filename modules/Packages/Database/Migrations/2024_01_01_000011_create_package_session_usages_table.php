<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_session_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('subscription_id')->index();
            $table->uuid('service_id')->index();
            $table->uuid('appointment_id')->nullable()->index();
            $table->timestamp('used_at')->index();
            $table->uuid('used_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('package_subscriptions')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();

            $table->index(['tenant_id', 'subscription_id']);
            $table->index(['tenant_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_session_usages');
    }
};
