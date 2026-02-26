<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('asset_type_id')->nullable()->after('category_id');
            $table->boolean('is_asset')->default(false)->after('asset_type_id');

            $table->index(['tenant_id', 'is_asset']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_asset']);
            $table->dropColumn(['asset_type_id', 'is_asset']);
        });
    }
};
