<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Setting;

class DefaultSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates default settings for the tenant.
     */
    public function run(): void
    {
        $settings = $this->getDefaultSettings();

        foreach ($settings as $key => $config) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $config['value'],
                    'group' => $config['group'],
                    'type' => $config['type'] ?? 'string',
                    'is_public' => $config['is_public'] ?? false,
                    'is_locked' => $config['is_locked'] ?? false,
                ]
            );
        }

        $this->command?->info('Default settings created successfully: ' . count($settings) . ' settings.');
    }

    /**
     * Get the default settings configuration.
     */
    protected function getDefaultSettings(): array
    {
        return [
            // General Settings
            'clinic_name' => [
                'value' => 'My Clinic',
                'group' => 'general',
                'type' => 'string',
            ],
            'clinic_tagline' => [
                'value' => '',
                'group' => 'general',
                'type' => 'string',
            ],
            'clinic_address' => [
                'value' => '',
                'group' => 'general',
                'type' => 'string',
            ],
            'clinic_phone' => [
                'value' => '',
                'group' => 'general',
                'type' => 'string',
            ],
            'clinic_email' => [
                'value' => '',
                'group' => 'general',
                'type' => 'string',
            ],
            'clinic_website' => [
                'value' => '',
                'group' => 'general',
                'type' => 'string',
            ],
            'default_language' => [
                'value' => 'en',
                'group' => 'general',
                'type' => 'string',
            ],
            'timezone' => [
                'value' => 'Africa/Cairo',
                'group' => 'general',
                'type' => 'string',
            ],
            'currency_code' => [
                'value' => 'EGP',
                'group' => 'general',
                'type' => 'string',
            ],
            'date_format' => [
                'value' => 'd/m/Y',
                'group' => 'general',
                'type' => 'string',
            ],
            'time_format' => [
                'value' => 'h:i A',
                'group' => 'general',
                'type' => 'string',
            ],

            // Booking Settings
            'default_slot_duration' => [
                'value' => '30',
                'group' => 'booking',
                'type' => 'integer',
            ],
            'buffer_minutes' => [
                'value' => '15',
                'group' => 'booking',
                'type' => 'integer',
            ],
            'max_advance_booking_days' => [
                'value' => '90',
                'group' => 'booking',
                'type' => 'integer',
            ],
            'cancellation_policy_hours' => [
                'value' => '24',
                'group' => 'booking',
                'type' => 'integer',
            ],
            'auto_confirm_appointments' => [
                'value' => '0',
                'group' => 'booking',
                'type' => 'boolean',
            ],
            'allow_online_booking' => [
                'value' => '1',
                'group' => 'booking',
                'type' => 'boolean',
            ],
            'require_deposit' => [
                'value' => '0',
                'group' => 'booking',
                'type' => 'boolean',
            ],
            'deposit_percentage' => [
                'value' => '25',
                'group' => 'booking',
                'type' => 'integer',
            ],
            'reminder_hours_before' => [
                'value' => '24',
                'group' => 'booking',
                'type' => 'integer',
            ],
            'send_whatsapp_reminders' => [
                'value' => '1',
                'group' => 'booking',
                'type' => 'boolean',
            ],
            'send_sms_reminders' => [
                'value' => '0',
                'group' => 'booking',
                'type' => 'boolean',
            ],
            'send_email_reminders' => [
                'value' => '1',
                'group' => 'booking',
                'type' => 'boolean',
            ],

            // Billing Settings
            'tax_rate' => [
                'value' => '14',
                'group' => 'billing',
                'type' => 'float',
            ],
            'tax_inclusive' => [
                'value' => '1',
                'group' => 'billing',
                'type' => 'boolean',
            ],
            'tax_registration_number' => [
                'value' => '',
                'group' => 'billing',
                'type' => 'string',
            ],
            'auto_invoice_on_complete' => [
                'value' => '1',
                'group' => 'billing',
                'type' => 'boolean',
            ],
            'default_payment_terms_days' => [
                'value' => '0',
                'group' => 'billing',
                'type' => 'integer',
            ],
            'enable_installments' => [
                'value' => '0',
                'group' => 'billing',
                'type' => 'boolean',
            ],
            'invoice_footer_note' => [
                'value' => 'Thank you for your business!',
                'group' => 'billing',
                'type' => 'string',
            ],
            'enabled_payment_methods' => [
                'value' => '["cash","card"]',
                'group' => 'billing',
                'type' => 'json',
            ],

            // Marketing Settings
            'send_birthday_greetings' => [
                'value' => '1',
                'group' => 'marketing',
                'type' => 'boolean',
            ],
            'send_followup_messages' => [
                'value' => '1',
                'group' => 'marketing',
                'type' => 'boolean',
            ],
            'followup_days_after' => [
                'value' => '7',
                'group' => 'marketing',
                'type' => 'integer',
            ],
            'enable_loyalty_program' => [
                'value' => '0',
                'group' => 'marketing',
                'type' => 'boolean',
            ],
            'points_per_currency' => [
                'value' => '1',
                'group' => 'marketing',
                'type' => 'integer',
            ],
            'currency_per_point' => [
                'value' => '0.1',
                'group' => 'marketing',
                'type' => 'float',
            ],
        ];
    }
}
