<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add type column if not exists
        if (!Schema::hasColumn('payments', 'type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('type', 20)->default('receive')->after('code');
                $table->index('type');
            });
        }

        // Add vendor_bill_id if not exists
        if (!Schema::hasColumn('payments', 'vendor_bill_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->uuid('vendor_bill_id')->nullable()->after('invoice_id');
                $table->index('vendor_bill_id');
            });
        }

        // Add supplier_id if not exists
        if (!Schema::hasColumn('payments', 'supplier_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->uuid('supplier_id')->nullable()->after('patient_id');
                $table->index('supplier_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['vendor_bill_id']);
            $table->dropIndex(['supplier_id']);
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'vendor_bill_id', 'supplier_id']);
        });
    }
};
