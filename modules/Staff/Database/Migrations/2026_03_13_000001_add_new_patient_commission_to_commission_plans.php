<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('commission_plans', function (Blueprint $table) {
            $table->boolean('new_patient_commission_enabled')->default(false)->after('default_flat_amount_minor');
            $table->string('new_patient_commission_type')->default('percentage')->after('new_patient_commission_enabled');
            $table->decimal('new_patient_percentage', 5, 2)->nullable()->after('new_patient_commission_type');
            $table->integer('new_patient_flat_amount_minor')->default(0)->after('new_patient_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('commission_plans', function (Blueprint $table) {
            $table->dropColumn([
                'new_patient_commission_enabled',
                'new_patient_commission_type',
                'new_patient_percentage',
                'new_patient_flat_amount_minor',
            ]);
        });
    }
};
