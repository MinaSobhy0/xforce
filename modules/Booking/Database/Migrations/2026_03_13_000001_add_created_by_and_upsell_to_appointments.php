<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('quantity');
            $table->boolean('is_upsell')->default(false)->after('created_by_user_id');

            $table->index('created_by_user_id');
            $table->index('is_upsell');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['created_by_user_id']);
            $table->dropIndex(['is_upsell']);
            $table->dropColumn(['created_by_user_id', 'is_upsell']);
        });
    }
};
