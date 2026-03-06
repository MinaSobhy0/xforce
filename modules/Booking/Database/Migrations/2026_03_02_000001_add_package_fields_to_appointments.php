<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('package_subscription_id')->nullable()->after('equipment_id');
            $table->boolean('is_package_session')->default(false)->after('package_subscription_id');

            $table->foreign('package_subscription_id')
                ->references('id')
                ->on('package_subscriptions')
                ->nullOnDelete();

            $table->index(['tenant_id', 'package_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['package_subscription_id']);
            $table->dropIndex(['tenant_id', 'package_subscription_id']);
            $table->dropColumn(['package_subscription_id', 'is_package_session']);
        });
    }
};
