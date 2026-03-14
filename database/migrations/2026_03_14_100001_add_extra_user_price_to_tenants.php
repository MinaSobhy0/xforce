<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->integer('extra_user_price')->nullable()->after('extra_storage_mb')
                ->comment('Price per additional user in EGP (null = use platform default)');
            $table->integer('extra_branch_price')->nullable()->after('extra_user_price')
                ->comment('Price per additional branch in EGP (null = use platform default)');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['extra_user_price', 'extra_branch_price']);
        });
    }
};
