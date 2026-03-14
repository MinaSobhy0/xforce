<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->jsonb('prices')->nullable()->after('yearly_price')
                ->comment('Country-specific prices: {"EG": {"monthly": 250, "yearly": 2500, "currency": "EGP"}, ...}');
        });
    }

    public function down(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->dropColumn('prices');
        });
    }
};
