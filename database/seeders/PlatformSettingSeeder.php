<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['group' => 'general', 'key' => 'platform_name', 'value' => 'XLinic', 'type' => 'string'],
            ['group' => 'general', 'key' => 'platform_url', 'value' => 'https://xlinic.com', 'type' => 'string'],
            ['group' => 'general', 'key' => 'support_email', 'value' => 'support@xlinic.com', 'type' => 'string'],
            ['group' => 'general', 'key' => 'default_locale', 'value' => 'ar', 'type' => 'string'],
            ['group' => 'general', 'key' => 'default_timezone', 'value' => 'Africa/Cairo', 'type' => 'string'],
            ['group' => 'general', 'key' => 'default_currency', 'value' => 'EGP', 'type' => 'string'],

            // Trial & Onboarding
            ['group' => 'trial', 'key' => 'trial_duration', 'value' => '14', 'type' => 'integer'],
            ['group' => 'trial', 'key' => 'default_trial_plan', 'value' => 'professional', 'type' => 'string'],
            ['group' => 'trial', 'key' => 'auto_provision', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'trial', 'key' => 'require_approval', 'value' => '0', 'type' => 'boolean'],
            ['group' => 'trial', 'key' => 'trial_expiry_warning', 'value' => '3', 'type' => 'integer'],
            ['group' => 'trial', 'key' => 'auto_suspend_after', 'value' => '7', 'type' => 'integer'],
            ['group' => 'trial', 'key' => 'auto_delete_after', 'value' => '90', 'type' => 'integer'],

            // Payment
            ['group' => 'payment', 'key' => 'payment_gateway', 'value' => 'paymob', 'type' => 'string'],
            ['group' => 'payment', 'key' => 'auto_charge', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'payment', 'key' => 'grace_period', 'value' => '7', 'type' => 'integer'],
            ['group' => 'payment', 'key' => 'retry_attempts', 'value' => '3', 'type' => 'integer'],
            ['group' => 'payment', 'key' => 'invoice_prefix', 'value' => 'PLT-', 'type' => 'string'],
            ['group' => 'payment', 'key' => 'vat_rate', 'value' => '14', 'type' => 'integer'],

            // Branding
            ['group' => 'branding', 'key' => 'primary_color', 'value' => '#2563EB', 'type' => 'string'],
            ['group' => 'branding', 'key' => 'footer_text', 'value' => '© 2025 XLinic. All rights reserved.', 'type' => 'string'],

            // Backup
            ['group' => 'backup', 'key' => 'auto_backup', 'value' => '1', 'type' => 'boolean'],
            ['group' => 'backup', 'key' => 'backup_frequency', 'value' => 'daily', 'type' => 'string'],
            ['group' => 'backup', 'key' => 'backup_time', 'value' => '02:00', 'type' => 'string'],
            ['group' => 'backup', 'key' => 'backup_retention', 'value' => '30', 'type' => 'integer'],
            ['group' => 'backup', 'key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean'],
            ['group' => 'backup', 'key' => 'maintenance_message', 'value' => "We'll be back shortly...", 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
