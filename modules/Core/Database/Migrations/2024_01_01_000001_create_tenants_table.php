<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('database_name');
            $table->string('database_host')->nullable();
            $table->integer('database_port')->nullable();
            $table->string('database_username')->nullable();
            $table->string('database_password')->nullable();
            $table->enum('status', ['active', 'suspended', 'inactive', 'pending', 'expired'])->default('pending');
            $table->json('settings')->nullable();
            $table->json('features')->nullable();
            $table->string('subscription_plan')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->integer('max_users')->default(10);
            $table->integer('max_branches')->default(1);
            $table->integer('max_patients')->default(1000);
            $table->integer('max_storage_mb')->default(1024);
            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('en');
            $table->string('currency', 3)->default('EGP');
            $table->decimal('tax_rate', 6, 4)->default(14.0000);
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('EG');
            $table->string('postal_code')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->text('custom_css')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['subscription_expires_at']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};