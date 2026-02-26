<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gc_batch_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('batch_code')->index();
            $table->foreignId('template_id')->nullable();
            $table->integer('quantity');
            $table->string('export_format')->nullable();  // csv, pdf, excel
            $table->json('card_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('exported_by')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'batch_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gc_batch_exports');
    }
};
