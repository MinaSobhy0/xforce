<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('host');
            $table->integer('port')->default(8069);
            $table->string('database_name');
            $table->text('username'); // Encrypted
            $table->text('password'); // Encrypted
            $table->text('api_key')->nullable(); // Encrypted, optional
            $table->string('protocol')->default('xmlrpc'); // xmlrpc or rest
            $table->boolean('use_ssl')->default(true);
            $table->integer('timeout')->default(30); // Request timeout in seconds
            $table->integer('rate_limit_per_minute')->default(60);
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_connections');
    }
};
