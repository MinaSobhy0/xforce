<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('service_categories', 'icon')) {
                $table->string('icon', 100)->nullable()->after('description');
            }
            if (!Schema::hasColumn('service_categories', 'color')) {
                $table->string('color', 50)->nullable()->after('icon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
