<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            // Financial totals (cached for performance)
            $table->integer('total_value_minor')->default(0)->after('cancelled_at');
            $table->integer('total_deposits_minor')->default(0)->after('total_value_minor');
            $table->integer('total_invoiced_minor')->default(0)->after('total_deposits_minor');
            $table->integer('total_paid_minor')->default(0)->after('total_invoiced_minor');
            $table->integer('balance_minor')->default(0)->after('total_paid_minor');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn([
                'total_value_minor',
                'total_deposits_minor',
                'total_invoiced_minor',
                'total_paid_minor',
                'balance_minor',
            ]);
        });
    }
};
