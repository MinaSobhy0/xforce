<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->jsonb('name'); // Translatable
            $table->jsonb('description')->nullable(); // Translatable
            $table->integer('price_monthly_minor')->default(0); // Price in piasters/cents
            $table->integer('price_yearly_minor')->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->integer('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            // Hard limits (null = unlimited)
            $table->integer('max_users')->nullable();
            $table->integer('max_branches')->nullable();
            $table->integer('max_patients')->nullable();
            $table->integer('max_storage_mb')->nullable();
            $table->integer('max_equipment')->nullable();
            $table->integer('max_products')->nullable();
            $table->integer('max_treatments')->nullable();
            $table->integer('max_api_calls_daily')->nullable();

            // Soft limits (monthly, null = unlimited)
            $table->integer('max_appointments_monthly')->nullable();
            $table->integer('max_whatsapp_monthly')->nullable();
            $table->integer('max_sms_monthly')->nullable();
            $table->integer('max_emails_monthly')->nullable();
            $table->integer('max_campaign_recipients')->nullable();

            // Overage pricing (in minor units per item)
            $table->integer('overage_appointment_minor')->nullable();
            $table->integer('overage_whatsapp_minor')->nullable();
            $table->integer('overage_sms_minor')->nullable();
            $table->integer('overage_email_minor')->nullable();
            $table->integer('overage_storage_gb_minor')->nullable();

            // Feature flags
            $table->boolean('allow_white_label')->default(false);
            $table->boolean('allow_custom_domain')->default(false);
            $table->boolean('allow_data_export')->default(true);
            $table->boolean('allow_api_access')->default(false);
            $table->boolean('has_priority_support')->default(false);
            $table->integer('data_retention_days')->default(365);
            $table->integer('max_concurrent_sessions')->nullable();

            // Included modules
            $table->jsonb('included_module_codes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
