<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('branch_id')->nullable()->index();

            // Identification
            $table->string('code', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);

            // Contact Information
            $table->string('email')->nullable()->index();
            $table->string('phone', 20)->index();
            $table->string('secondary_phone', 20)->nullable();

            // Personal Information
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('national_id', 50)->nullable()->index();

            // Address
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Egypt');
            $table->string('postal_code', 20)->nullable();

            // Additional Info
            $table->string('occupation', 100)->nullable();

            // Emergency Contact
            $table->string('emergency_contact_name', 200)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();

            // Referral
            $table->string('referral_source', 50)->nullable()->index();
            $table->foreignId('referred_by_patient_id')->nullable();
            $table->string('referred_by_name', 200)->nullable();

            // Preferences
            $table->string('language', 10)->default('ar');

            // Status
            $table->enum('status', ['active', 'inactive', 'blocked', 'deceased'])->default('active')->index();

            // Tags & Notes
            $table->jsonb('tags')->nullable();
            $table->text('notes')->nullable();

            // Portal Access
            $table->boolean('portal_access_enabled')->default(true);
            $table->string('portal_password')->nullable();

            // Statistics
            $table->timestamp('last_visit_at')->nullable();
            $table->integer('total_visits')->default(0);
            $table->bigInteger('total_spent_minor')->default(0);
            $table->integer('loyalty_points')->default(0);

            // Marketing Consent
            $table->boolean('marketing_consent')->default(false);
            $table->boolean('sms_consent')->default(true);
            $table->boolean('email_consent')->default(true);
            $table->boolean('whatsapp_consent')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common searches
            $table->index(['first_name', 'last_name']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'last_visit_at']);
        });

        // Add self-referencing foreign key after table creation
        Schema::table('patients', function (Blueprint $table) {
            $table->foreign('referred_by_patient_id')
                ->references('id')
                ->on('patients')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['referred_by_patient_id']);
        });
        Schema::dropIfExists('patients');
    }
};
