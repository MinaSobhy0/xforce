<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->uuid('tenant_id')->nullable()->index();
            $table->bigInteger('current_number')->default(0);
            $table->timestamps();

            $table->unique(['code', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
