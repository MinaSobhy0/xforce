<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Queue jobs that exhaust their retries were silently deleted — there
     * was no failed_jobs table anywhere, so lost RealtimeSyncJobs (e.g.
     * during a rate-limit storm) vanished without a trace. Standard Laravel
     * failed-jobs schema, created in the tenant schema alongside jobs.
     */
    public function up(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
