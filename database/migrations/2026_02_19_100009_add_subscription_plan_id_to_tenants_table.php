<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->uuid('subscription_plan_id')->nullable()->after('subscription_plan');
            $table->uuid('owner_user_id')->nullable()->after('id');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_expires_at');
            $table->string('subscription_status', 20)->default('pending')->after('status');

            $table->foreign('subscription_plan_id')
                ->references('id')
                ->on('subscription_plans')
                ->onDelete('set null');

            $table->index(['subscription_plan_id']);
            $table->index(['subscription_status']);
            $table->index(['trial_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn(['subscription_plan_id', 'owner_user_id', 'trial_ends_at', 'subscription_status']);
        });
    }
};
