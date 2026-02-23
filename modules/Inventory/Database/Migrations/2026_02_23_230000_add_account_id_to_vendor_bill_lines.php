<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vendor_bill_lines', 'account_id')) {
            Schema::table('vendor_bill_lines', function (Blueprint $table) {
                $table->uuid('account_id')->nullable()->after('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_bill_lines', function (Blueprint $table) {
            $table->dropColumn('account_id');
        });
    }
};
