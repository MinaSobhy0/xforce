<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make weekly_hours nullable for flexible schedules
        DB::statement('ALTER TABLE work_schedules ALTER COLUMN weekly_hours DROP NOT NULL');
    }

    public function down(): void
    {
        // Restore NOT NULL constraint
        DB::statement("UPDATE work_schedules SET weekly_hours = '[]' WHERE weekly_hours IS NULL");
        DB::statement('ALTER TABLE work_schedules ALTER COLUMN weekly_hours SET NOT NULL');
    }
};
