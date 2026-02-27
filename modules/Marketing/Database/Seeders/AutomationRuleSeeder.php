<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketing\Models\AutomationRule;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Core\Database\Seeders\Concerns\ResolveTenantId;

class AutomationRuleSeeder extends Seeder
{
    use ResolveTenantId;

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();

        // Get template IDs by code
        $templates = MessageTemplate::where('tenant_id', $tenantId)
            ->whereIn('code', [
                'APPT_CONFIRM_WA',
                'APPT_REMIND_WA',
                'APPT_FOLLOWUP_WA',
                'INVOICE_WA',
                'PAYMENT_REMIND_WA',
                'BIRTHDAY_WA',
            ])
            ->pluck('id', 'code');

        $rules = [
            // Appointment Confirmation - Immediate
            [
                'name' => ['en' => 'Send Appointment Confirmation', 'ar' => 'إرسال تأكيد الموعد'],
                'description' => ['en' => 'Send WhatsApp confirmation when appointment is confirmed', 'ar' => 'إرسال تأكيد واتساب عند تأكيد الموعد'],
                'trigger_type' => AutomationRule::TRIGGER_APPOINTMENT_CONFIRMED,
                'template_code' => 'APPT_CONFIRM_WA',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'timing_type' => AutomationRule::TIMING_IMMEDIATE,
                'timing_value' => 0,
                'timing_unit' => 'hours',
                'priority' => 100,
            ],

            // Appointment Reminder - 24 hours before
            [
                'name' => ['en' => 'Appointment Reminder (24h)', 'ar' => 'تذكير بالموعد (24 ساعة)'],
                'description' => ['en' => 'Send reminder 24 hours before appointment', 'ar' => 'إرسال تذكير قبل الموعد بـ 24 ساعة'],
                'trigger_type' => AutomationRule::TRIGGER_APPOINTMENT_REMINDER,
                'template_code' => 'APPT_REMIND_WA',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'timing_type' => AutomationRule::TIMING_BEFORE,
                'timing_value' => 24,
                'timing_unit' => 'hours',
                'priority' => 90,
            ],

            // Appointment Reminder - 2 hours before
            [
                'name' => ['en' => 'Appointment Reminder (2h)', 'ar' => 'تذكير بالموعد (ساعتين)'],
                'description' => ['en' => 'Send reminder 2 hours before appointment', 'ar' => 'إرسال تذكير قبل الموعد بساعتين'],
                'trigger_type' => AutomationRule::TRIGGER_APPOINTMENT_REMINDER,
                'template_code' => 'APPT_REMIND_WA',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'timing_type' => AutomationRule::TIMING_BEFORE,
                'timing_value' => 2,
                'timing_unit' => 'hours',
                'priority' => 80,
            ],

            // Follow-up - 2 hours after completion
            [
                'name' => ['en' => 'Post-Appointment Follow-up', 'ar' => 'متابعة بعد الموعد'],
                'description' => ['en' => 'Send follow-up message 2 hours after appointment completion', 'ar' => 'إرسال رسالة متابعة بعد ساعتين من انتهاء الموعد'],
                'trigger_type' => AutomationRule::TRIGGER_APPOINTMENT_COMPLETED,
                'template_code' => 'APPT_FOLLOWUP_WA',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'timing_type' => AutomationRule::TIMING_AFTER,
                'timing_value' => 2,
                'timing_unit' => 'hours',
                'priority' => 70,
            ],

            // Invoice Receipt - Immediate
            [
                'name' => ['en' => 'Send Invoice Receipt', 'ar' => 'إرسال إيصال الفاتورة'],
                'description' => ['en' => 'Send receipt when invoice is paid', 'ar' => 'إرسال إيصال عند دفع الفاتورة'],
                'trigger_type' => AutomationRule::TRIGGER_INVOICE_PAID,
                'template_code' => 'INVOICE_WA',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'timing_type' => AutomationRule::TIMING_IMMEDIATE,
                'timing_value' => 0,
                'timing_unit' => 'hours',
                'priority' => 60,
            ],

            // Payment Reminder - 7 days after overdue
            [
                'name' => ['en' => 'Payment Reminder', 'ar' => 'تذكير بالدفع'],
                'description' => ['en' => 'Send payment reminder 7 days after invoice becomes overdue', 'ar' => 'إرسال تذكير بالدفع بعد 7 أيام من تأخر الفاتورة'],
                'trigger_type' => AutomationRule::TRIGGER_INVOICE_OVERDUE,
                'template_code' => 'PAYMENT_REMIND_WA',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'timing_type' => AutomationRule::TIMING_AFTER,
                'timing_value' => 7,
                'timing_unit' => 'days',
                'priority' => 50,
            ],

            // Birthday - On birthday morning
            [
                'name' => ['en' => 'Birthday Wishes', 'ar' => 'تهنئة بعيد الميلاد'],
                'description' => ['en' => 'Send birthday wishes on patient birthday', 'ar' => 'إرسال تهنئة في عيد ميلاد المريض'],
                'trigger_type' => AutomationRule::TRIGGER_PATIENT_BIRTHDAY,
                'template_code' => 'BIRTHDAY_WA',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'timing_type' => AutomationRule::TIMING_IMMEDIATE,
                'timing_value' => 0,
                'timing_unit' => 'hours',
                'priority' => 40,
            ],
        ];

        foreach ($rules as $rule) {
            $templateCode = $rule['template_code'];
            unset($rule['template_code']);

            $templateId = $templates[$templateCode] ?? null;

            $existing = AutomationRule::where('tenant_id', $tenantId)
                ->where('trigger_type', $rule['trigger_type'])
                ->where('timing_type', $rule['timing_type'])
                ->where('timing_value', $rule['timing_value'])
                ->first();

            if (!$existing) {
                AutomationRule::create(array_merge($rule, [
                    'tenant_id' => $tenantId,
                    'template_id' => $templateId,
                    'is_active' => false, // Disabled by default, admin needs to configure and enable
                ]));
            }
        }
    }
}
