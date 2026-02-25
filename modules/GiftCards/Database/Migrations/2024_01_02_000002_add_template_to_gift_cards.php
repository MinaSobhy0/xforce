<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->uuid('template_id')->nullable()->after('tenant_id');
            $table->uuid('assigned_to_staff_id')->nullable()->after('recipient_patient_id');
            $table->timestamp('assigned_at')->nullable()->after('assigned_to_staff_id');
            $table->uuid('sold_by_staff_id')->nullable()->after('assigned_at');
            $table->string('encrypted_code')->nullable()->after('code');
            $table->string('code_hash')->nullable()->after('encrypted_code');
            $table->string('pin_code')->nullable()->after('code_hash');
            $table->uuid('sale_journal_entry_id')->nullable()->after('purchased_via_invoice_id');

            $table->index(['tenant_id', 'template_id']);
            $table->index(['tenant_id', 'assigned_to_staff_id']);
            $table->index('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'template_id']);
            $table->dropIndex(['tenant_id', 'assigned_to_staff_id']);
            $table->dropIndex(['code_hash']);

            $table->dropColumn([
                'template_id',
                'assigned_to_staff_id',
                'assigned_at',
                'sold_by_staff_id',
                'encrypted_code',
                'code_hash',
                'pin_code',
                'sale_journal_entry_id',
            ]);
        });
    }
};
