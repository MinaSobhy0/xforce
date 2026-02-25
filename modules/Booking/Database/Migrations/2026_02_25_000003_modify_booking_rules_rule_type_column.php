<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For PostgreSQL, we need to drop the enum constraints and change to varchar

        // Change rule_type from enum to varchar
        DB::statement('ALTER TABLE booking_rules ALTER COLUMN rule_type TYPE varchar(50)');
        DB::statement('ALTER TABLE booking_rules DROP CONSTRAINT IF EXISTS booking_rules_rule_type_check');

        // Change scope_level from enum to varchar (model has 5 levels, migration only had 3)
        DB::statement('ALTER TABLE booking_rules ALTER COLUMN scope_level TYPE varchar(30)');
        DB::statement('ALTER TABLE booking_rules DROP CONSTRAINT IF EXISTS booking_rules_scope_level_check');
    }

    public function down(): void
    {
        // Reverting would require cleaning up data first, so we'll leave it as varchar
        // The model validation handles valid values
    }
};
