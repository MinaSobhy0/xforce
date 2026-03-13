<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->integer('sold_price_minor')->nullable()->after('remaining_value_minor');
            $table->integer('template_discount_minor')->default(0)->after('sold_price_minor');
            $table->integer('extra_discount_minor')->default(0)->after('template_discount_minor');
            $table->integer('total_discount_minor')->default(0)->after('extra_discount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn(['sold_price_minor', 'template_discount_minor', 'extra_discount_minor', 'total_discount_minor']);
        });
    }
};
