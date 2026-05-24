<?php

namespace Database\Seeders;

use App\Models\PlatformWhatsAppTemplate;
use Illuminate\Database\Seeder;
use Modules\Marketing\Models\MessageTemplate;

/**
 * Seeds the platform WhatsApp template catalog with the 6 ready-to-adopt
 * blueprints that every clinic needs out of the box. Source: the
 * existing MessageTemplateSeeder in modules/Marketing/Database/Seeders
 * (tenant-scoped, lived inside each tenant schema). Lifting them up to
 * the platform catalog means clinic admins adopt + submit once via the
 * Catalog UI, instead of each tenant carrying a private copy.
 *
 * Meta template-naming rules applied: lowercase, snake_case, no
 * trailing _WA suffix (Meta enforces [a-z0-9_]).
 *
 * Body content matches the existing seeder verbatim (EN + AR) but I
 * verified each one's opening doesn't lead with a variable (Meta
 * rejects "{{patient_name}}..." but accepts "Hi {{patient_name}}, ...").
 *
 * Idempotent: re-running upserts on `code`. Categories chosen per Meta's
 * 2024 policy — transactional → UTILITY, promotional → MARKETING.
 *
 * Invoke manually:  php artisan db:seed --class=PlatformWhatsAppTemplateSeeder
 */
class PlatformWhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'appointment_confirmation',
                'category' => MessageTemplate::META_CATEGORY_UTILITY,
                'name' => ['en' => 'Appointment Confirmation', 'ar' => 'تأكيد الموعد'],
                'description' => ['en' => 'Sent immediately after a booking is confirmed.'],
                'body' => [
                    'en' => "Hello {{patient_name}}, your appointment has been confirmed.\n\n📅 Date: {{appointment_date}}\n⏰ Time: {{appointment_time}}\n🏥 Service: {{service_name}}\n👨‍⚕️ Doctor: {{practitioner_name}}\n📍 Branch: {{branch_name}}\n\nPlease arrive 10 minutes early.",
                    'ar' => "مرحباً {{patient_name}}، تم تأكيد موعدك.\n\n📅 التاريخ: {{appointment_date}}\n⏰ الوقت: {{appointment_time}}\n🏥 الخدمة: {{service_name}}\n👨‍⚕️ الطبيب: {{practitioner_name}}\n📍 الفرع: {{branch_name}}\n\nيرجى الحضور قبل الموعد بـ 10 دقائق.",
                ],
                'footer' => ['en' => 'Reply to reschedule or cancel.', 'ar' => 'للتعديل أو الإلغاء، يرجى التواصل معنا.'],
                'buttons_json' => [
                    ['label' => 'Confirm', 'action' => 'confirm_appointment'],
                    ['label' => 'Reschedule', 'action' => 'reschedule_appointment'],
                ],
                'variables_json' => [
                    'patient_name' => 'Sara Ahmed',
                    'appointment_date' => '2026-06-01',
                    'appointment_time' => '10:00 AM',
                    'service_name' => 'Laser Consultation',
                    'practitioner_name' => 'Dr. Hany',
                    'branch_name' => 'Cairo Downtown',
                ],
                'sort_order' => 1,
            ],

            [
                'code' => 'appointment_reminder',
                'category' => MessageTemplate::META_CATEGORY_UTILITY,
                'name' => ['en' => 'Appointment Reminder', 'ar' => 'تذكير بالموعد'],
                'description' => ['en' => 'Sent 24 hours before the appointment.'],
                'body' => [
                    'en' => "Hi {{patient_name}}, this is a reminder about your upcoming appointment.\n\n📅 {{appointment_date}}\n⏰ {{appointment_time}}\n🏥 {{service_name}}\n\nSee you soon!",
                    'ar' => "مرحباً {{patient_name}}، هذا تذكير بموعدك القادم.\n\n📅 {{appointment_date}}\n⏰ {{appointment_time}}\n🏥 {{service_name}}\n\nنراك قريباً!",
                ],
                'buttons_json' => [
                    ['label' => 'Confirm', 'action' => 'confirm_appointment'],
                    ['label' => 'Reschedule', 'action' => 'reschedule_appointment'],
                    ['label' => 'Cancel', 'action' => 'cancel_appointment'],
                ],
                'variables_json' => [
                    'patient_name' => 'Sara Ahmed',
                    'appointment_date' => '2026-06-01',
                    'appointment_time' => '10:00 AM',
                    'service_name' => 'Laser Consultation',
                ],
                'sort_order' => 2,
            ],

            [
                'code' => 'appointment_followup',
                'category' => MessageTemplate::META_CATEGORY_UTILITY,
                'name' => ['en' => 'Appointment Follow-up', 'ar' => 'متابعة بعد الموعد'],
                'description' => ['en' => 'Sent the day after an appointment for post-care + satisfaction.'],
                'body' => [
                    'en' => "Hi {{patient_name}}, thank you for visiting us today! We hope your {{service_name}} session went well.\n\nIf you have any questions or concerns, please reply to this message.\n\nWe look forward to seeing you again!",
                    'ar' => "مرحباً {{patient_name}}، شكراً لزيارتك اليوم! نأمل أن تكون جلسة {{service_name}} قد سارت بشكل جيد.\n\nإذا كان لديك أي أسئلة أو استفسارات، رد على هذه الرسالة.\n\nنتطلع لرؤيتك مرة أخرى!",
                ],
                'variables_json' => [
                    'patient_name' => 'Sara Ahmed',
                    'service_name' => 'Laser Consultation',
                ],
                'sort_order' => 3,
            ],

            [
                'code' => 'invoice_receipt',
                'category' => MessageTemplate::META_CATEGORY_UTILITY,
                'name' => ['en' => 'Invoice Receipt', 'ar' => 'إيصال الفاتورة'],
                'description' => ['en' => 'Sent on successful payment of an invoice.'],
                'body' => [
                    'en' => "Hi {{patient_name}}, thank you for your payment.\n\n🧾 Invoice: {{invoice_number}}\n💰 Amount: {{invoice_total}}\n📅 Date: {{invoice_date}}\n\nKeep this message as your receipt.",
                    'ar' => "مرحباً {{patient_name}}، شكراً لك على الدفع.\n\n🧾 فاتورة رقم: {{invoice_number}}\n💰 المبلغ: {{invoice_total}}\n📅 التاريخ: {{invoice_date}}\n\nاحتفظ بهذه الرسالة كإيصال.",
                ],
                'variables_json' => [
                    'patient_name' => 'Sara Ahmed',
                    'invoice_number' => 'INV-2026-0042',
                    'invoice_total' => 'EGP 1,250.00',
                    'invoice_date' => '2026-06-01',
                ],
                'sort_order' => 4,
            ],

            [
                'code' => 'payment_reminder',
                'category' => MessageTemplate::META_CATEGORY_UTILITY,
                'name' => ['en' => 'Payment Reminder', 'ar' => 'تذكير بالدفع'],
                'description' => ['en' => 'Sent for overdue/outstanding balances.'],
                'body' => [
                    'en' => "Hi {{patient_name}}, this is a friendly reminder that you have an outstanding balance of {{outstanding_amount}} for invoice {{invoice_number}}.\n\nPlease contact us to arrange payment.",
                    'ar' => "مرحباً {{patient_name}}، هذا تذكير ودي بأن لديك رصيد مستحق بقيمة {{outstanding_amount}} للفاتورة رقم {{invoice_number}}.\n\nيرجى التواصل معنا لترتيب الدفع.",
                ],
                'variables_json' => [
                    'patient_name' => 'Sara Ahmed',
                    'outstanding_amount' => 'EGP 350.00',
                    'invoice_number' => 'INV-2026-0042',
                ],
                'sort_order' => 5,
            ],

            [
                'code' => 'birthday_wishes',
                'category' => MessageTemplate::META_CATEGORY_MARKETING,
                'name' => ['en' => 'Birthday Wishes', 'ar' => 'تهنئة بعيد الميلاد'],
                'description' => ['en' => 'Sent on the patient\'s birthday — promo offer + warm greeting.'],
                'body' => [
                    'en' => "Happy Birthday, {{patient_name}}! 🎂🎉\n\nWishing you a wonderful day filled with joy. As a birthday gift, enjoy a special discount on your next visit — contact us to learn more!",
                    'ar' => "عيد ميلاد سعيد {{patient_name}}! 🎂🎉\n\nنتمنى لك يوماً رائعاً مليئاً بالفرح. كهدية عيد ميلاد، استمتع بخصم خاص في زيارتك القادمة — تواصل معنا لمعرفة المزيد!",
                ],
                'variables_json' => [
                    'patient_name' => 'Sara Ahmed',
                ],
                'sort_order' => 6,
            ],
        ];

        foreach ($templates as $template) {
            PlatformWhatsAppTemplate::updateOrCreate(
                ['code' => $template['code']],
                array_merge($template, [
                    'default_language' => 'en_US',
                    'header_type' => 'none',
                    'is_active' => true,
                ])
            );
        }

        $this->command?->info('Seeded '.count($templates).' WhatsApp catalog templates.');
    }
}
