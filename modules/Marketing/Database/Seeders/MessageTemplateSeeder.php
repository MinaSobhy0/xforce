<?php

namespace Modules\Marketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Core\Database\Seeders\Concerns\ResolveTenantId;

class MessageTemplateSeeder extends Seeder
{
    use ResolveTenantId;

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();

        $templates = [
            // Appointment Confirmation
            [
                'code' => 'APPT_CONFIRM_WA',
                'name' => ['en' => 'Appointment Confirmation (WhatsApp)', 'ar' => 'تأكيد الموعد (واتساب)'],
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_APPOINTMENT_CONFIRMATION,
                'subject' => ['en' => null, 'ar' => null],
                'content' => [
                    'en' => "Hello {{patient_name}},\n\nYour appointment has been confirmed:\n\n📅 Date: {{appointment_date}}\n⏰ Time: {{appointment_time}}\n🏥 Service: {{service_name}}\n👨‍⚕️ Doctor: {{practitioner_name}}\n📍 Branch: {{branch_name}}\n\nPlease arrive 10 minutes early.\n\nTo reschedule or cancel, please contact us.",
                    'ar' => "مرحباً {{patient_name}}،\n\nتم تأكيد موعدك:\n\n📅 التاريخ: {{appointment_date}}\n⏰ الوقت: {{appointment_time}}\n🏥 الخدمة: {{service_name}}\n👨‍⚕️ الطبيب: {{practitioner_name}}\n📍 الفرع: {{branch_name}}\n\nيرجى الحضور قبل الموعد بـ 10 دقائق.\n\nللتعديل أو الإلغاء، يرجى التواصل معنا.",
                ],
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'APPT_CONFIRM_SMS',
                'name' => ['en' => 'Appointment Confirmation (SMS)', 'ar' => 'تأكيد الموعد (رسالة نصية)'],
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_APPOINTMENT_CONFIRMATION,
                'subject' => ['en' => null, 'ar' => null],
                'content' => [
                    'en' => "Your appointment is confirmed for {{appointment_date}} at {{appointment_time}}. Service: {{service_name}}. Please arrive 10 min early.",
                    'ar' => "تم تأكيد موعدك في {{appointment_date}} الساعة {{appointment_time}}. الخدمة: {{service_name}}. يرجى الحضور قبل 10 دقائق.",
                ],
                'is_system' => true,
                'sort_order' => 2,
            ],

            // Appointment Reminder
            [
                'code' => 'APPT_REMIND_WA',
                'name' => ['en' => 'Appointment Reminder (WhatsApp)', 'ar' => 'تذكير بالموعد (واتساب)'],
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_APPOINTMENT_REMINDER,
                'subject' => ['en' => null, 'ar' => null],
                'content' => [
                    'en' => "Hi {{patient_name}}! 👋\n\nThis is a reminder about your upcoming appointment:\n\n📅 {{appointment_date}}\n⏰ {{appointment_time}}\n🏥 {{service_name}}\n\nSee you soon!",
                    'ar' => "مرحباً {{patient_name}}! 👋\n\nهذا تذكير بموعدك القادم:\n\n📅 {{appointment_date}}\n⏰ {{appointment_time}}\n🏥 {{service_name}}\n\nنراك قريباً!",
                ],
                'is_system' => true,
                'sort_order' => 3,
            ],

            // Follow-up
            [
                'code' => 'APPT_FOLLOWUP_WA',
                'name' => ['en' => 'Appointment Follow-up (WhatsApp)', 'ar' => 'متابعة بعد الموعد (واتساب)'],
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_APPOINTMENT_FOLLOWUP,
                'subject' => ['en' => null, 'ar' => null],
                'content' => [
                    'en' => "Hi {{patient_name}},\n\nThank you for visiting us today! We hope your {{service_name}} session went well.\n\nIf you have any questions or concerns, please don't hesitate to contact us.\n\nWe look forward to seeing you again! 😊",
                    'ar' => "مرحباً {{patient_name}}،\n\nشكراً لزيارتك اليوم! نأمل أن تكون جلسة {{service_name}} قد سارت بشكل جيد.\n\nإذا كان لديك أي أسئلة أو استفسارات، لا تتردد في التواصل معنا.\n\nنتطلع لرؤيتك مرة أخرى! 😊",
                ],
                'is_system' => true,
                'sort_order' => 4,
            ],

            // Invoice/Receipt
            [
                'code' => 'INVOICE_WA',
                'name' => ['en' => 'Invoice Receipt (WhatsApp)', 'ar' => 'إيصال الفاتورة (واتساب)'],
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_INVOICE_RECEIPT,
                'subject' => ['en' => null, 'ar' => null],
                'content' => [
                    'en' => "Hi {{patient_name}},\n\n🧾 Invoice #{{invoice_number}}\n💰 Amount: {{invoice_total}}\n📅 Date: {{invoice_date}}\n\nThank you for your payment!",
                    'ar' => "مرحباً {{patient_name}}،\n\n🧾 فاتورة رقم #{{invoice_number}}\n💰 المبلغ: {{invoice_total}}\n📅 التاريخ: {{invoice_date}}\n\nشكراً لك على الدفع!",
                ],
                'is_system' => true,
                'sort_order' => 5,
            ],

            // Payment Reminder
            [
                'code' => 'PAYMENT_REMIND_WA',
                'name' => ['en' => 'Payment Reminder (WhatsApp)', 'ar' => 'تذكير بالدفع (واتساب)'],
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_PAYMENT_REMINDER,
                'subject' => ['en' => null, 'ar' => null],
                'content' => [
                    'en' => "Hi {{patient_name}},\n\nThis is a friendly reminder that you have an outstanding balance of {{outstanding_amount}} for invoice #{{invoice_number}}.\n\nPlease contact us to arrange payment. Thank you!",
                    'ar' => "مرحباً {{patient_name}}،\n\nهذا تذكير ودي بأن لديك رصيد مستحق بقيمة {{outstanding_amount}} للفاتورة رقم #{{invoice_number}}.\n\nيرجى التواصل معنا لترتيب الدفع. شكراً!",
                ],
                'is_system' => true,
                'sort_order' => 6,
            ],

            // Birthday
            [
                'code' => 'BIRTHDAY_WA',
                'name' => ['en' => 'Birthday Wishes (WhatsApp)', 'ar' => 'تهنئة بعيد الميلاد (واتساب)'],
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_BIRTHDAY,
                'subject' => ['en' => null, 'ar' => null],
                'content' => [
                    'en' => "Happy Birthday, {{patient_name}}! 🎂🎉\n\nWishing you a wonderful day filled with joy and happiness!\n\nAs a birthday gift, enjoy a special discount on your next visit. Contact us to learn more!",
                    'ar' => "عيد ميلاد سعيد {{patient_name}}! 🎂🎉\n\nنتمنى لك يوماً رائعاً مليئاً بالفرح والسعادة!\n\nكهدية عيد ميلاد، استمتع بخصم خاص في زيارتك القادمة. تواصل معنا لمعرفة المزيد!",
                ],
                'is_system' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($templates as $template) {
            $existing = MessageTemplate::where('code', $template['code'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$existing) {
                MessageTemplate::create(array_merge($template, [
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                ]));
            }
        }
    }
}
