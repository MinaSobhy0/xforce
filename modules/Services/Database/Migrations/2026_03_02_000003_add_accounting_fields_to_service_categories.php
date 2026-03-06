<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->foreignId('unearned_revenue_account_id')
                ->nullable()
                ->after('default_parameter_template_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();

            $table->foreignId('service_revenue_account_id')
                ->nullable()
                ->after('unearned_revenue_account_id')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_revenue_account_id');
            $table->dropConstrainedForeignId('unearned_revenue_account_id');
        });
    }
};
