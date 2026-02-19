<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('guard_name');
            $table->text('description')->nullable()->after('display_name');
            $table->integer('level')->default(0)->after('description');
            $table->boolean('is_system')->default(false)->after('level');
            $table->boolean('is_active')->default(true)->after('is_system');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('guard_name');
            $table->string('module')->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'level', 'is_system', 'is_active']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'module']);
        });
    }
};
