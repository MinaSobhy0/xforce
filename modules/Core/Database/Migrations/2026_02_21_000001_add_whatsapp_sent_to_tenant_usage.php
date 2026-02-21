<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_usage', function (Blueprint $table) {
            $table->integer('branches')->default(0)->after('users');
            $table->integer('whatsapp_sent')->default(0)->after('sms_sent');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_usage', function (Blueprint $table) {
            $table->dropColumn(['branches', 'whatsapp_sent']);
        });
    }
};
