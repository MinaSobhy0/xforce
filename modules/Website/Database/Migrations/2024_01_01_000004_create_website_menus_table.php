<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_menus', function (Blueprint $table) {
            $table->id();
            $table->string('location', 50)->unique()->comment('header, footer');
            $table->jsonb('items')->nullable()->comment('Menu items array');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_menus');
    }
};
