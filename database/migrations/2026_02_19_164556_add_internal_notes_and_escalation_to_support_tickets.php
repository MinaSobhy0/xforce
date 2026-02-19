<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('resolution_notes');
            }
            if (!Schema::hasColumn('support_tickets', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('internal_notes');
            }
            if (!Schema::hasColumn('support_tickets', 'escalated_to')) {
                $table->string('escalated_to')->nullable()->after('escalated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['internal_notes', 'escalated_at', 'escalated_to']);
        });
    }
};
