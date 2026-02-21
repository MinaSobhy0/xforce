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
        Schema::table('tenant_usage', function (Blueprint $table) {
            // Add missing columns for Usage tab in tenant profile
            if (!Schema::hasColumn('tenant_usage', 'equipment')) {
                $table->integer('equipment')->default(0)->after('treatments');
            }
            if (!Schema::hasColumn('tenant_usage', 'products')) {
                $table->integer('products')->default(0)->after('equipment');
            }

            // Storage breakdown
            if (!Schema::hasColumn('tenant_usage', 'storage_photos_mb')) {
                $table->integer('storage_photos_mb')->default(0)->after('storage_mb');
            }
            if (!Schema::hasColumn('tenant_usage', 'storage_documents_mb')) {
                $table->integer('storage_documents_mb')->default(0)->after('storage_photos_mb');
            }
            if (!Schema::hasColumn('tenant_usage', 'storage_consent_mb')) {
                $table->integer('storage_consent_mb')->default(0)->after('storage_documents_mb');
            }

            // Monthly usage counters
            if (!Schema::hasColumn('tenant_usage', 'appointments_this_month')) {
                $table->integer('appointments_this_month')->default(0)->after('reports_generated');
            }
            if (!Schema::hasColumn('tenant_usage', 'whatsapp_this_month')) {
                $table->integer('whatsapp_this_month')->default(0)->after('appointments_this_month');
            }
            if (!Schema::hasColumn('tenant_usage', 'sms_this_month')) {
                $table->integer('sms_this_month')->default(0)->after('whatsapp_this_month');
            }
            if (!Schema::hasColumn('tenant_usage', 'emails_this_month')) {
                $table->integer('emails_this_month')->default(0)->after('sms_this_month');
            }
            if (!Schema::hasColumn('tenant_usage', 'api_calls_today')) {
                $table->integer('api_calls_today')->default(0)->after('emails_this_month');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_usage', function (Blueprint $table) {
            $columns = [
                'equipment', 'products',
                'storage_photos_mb', 'storage_documents_mb', 'storage_consent_mb',
                'appointments_this_month', 'whatsapp_this_month', 'sms_this_month',
                'emails_this_month', 'api_calls_today'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('tenant_usage', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
