<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('add_ons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('icon')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_recurring')->default(true);
            $table->string('billing_interval')->default('monthly'); // monthly, yearly, one-time
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_add_ons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('add_on_id')->constrained()->onDelete('cascade');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('billing_interval')->default('monthly');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('active'); // active, expired, cancelled
            $table->timestamps();

            $table->unique(['tenant_id', 'add_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_add_ons');
        Schema::dropIfExists('add_ons');
    }
};
