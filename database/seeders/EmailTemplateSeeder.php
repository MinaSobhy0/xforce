<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => [
                    'en' => 'Welcome to {platform_name}!',
                    'ar' => 'مرحباً بك في {platform_name}!',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Welcome to {platform_name}! Your clinic <strong>{clinic_name}</strong> is now ready.</p><p>You can login at: <a href="{login_url}">{login_url}</a></p><p>Your trial ends on {trial_end_date}.</p><p>Best regards,<br>{platform_name} Team</p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>أهلاً بك في {platform_name}! عيادتك <strong>{clinic_name}</strong> جاهزة الآن.</p><p>يمكنك تسجيل الدخول من: <a href="{login_url}">{login_url}</a></p><p>تنتهي الفترة التجريبية في {trial_end_date}.</p><p>مع أطيب التحيات،<br>فريق {platform_name}</p>',
                ],
                'trigger' => 'on_signup',
                'variables' => ['platform_name', 'owner_name', 'clinic_name', 'login_url', 'trial_end_date'],
            ],
            [
                'code' => 'trial_expiring',
                'name' => 'Trial Expiring Soon',
                'subject' => [
                    'en' => 'Your trial expires in {days} days',
                    'ar' => 'تنتهي فترتك التجريبية خلال {days} أيام',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Your free trial for <strong>{clinic_name}</strong> expires in {days} days.</p><p>Upgrade now to keep all your data and continue using {platform_name}.</p><p><a href="{login_url}">Upgrade Now</a></p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>تنتهي فترتك التجريبية المجانية لـ <strong>{clinic_name}</strong> خلال {days} أيام.</p><p>قم بالترقية الآن للحفاظ على بياناتك ومتابعة استخدام {platform_name}.</p>',
                ],
                'trigger' => 'trial_expiring',
                'variables' => ['owner_name', 'clinic_name', 'days', 'login_url', 'platform_name'],
            ],
            [
                'code' => 'trial_expired',
                'name' => 'Trial Expired',
                'subject' => [
                    'en' => 'Your trial has expired',
                    'ar' => 'انتهت فترتك التجريبية',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Your free trial for <strong>{clinic_name}</strong> has expired.</p><p>Your account is now suspended. Upgrade to restore access to your data.</p><p><a href="{login_url}">Restore Access</a></p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>انتهت فترتك التجريبية لـ <strong>{clinic_name}</strong>.</p><p>تم تعليق حسابك. قم بالترقية لاستعادة الوصول إلى بياناتك.</p>',
                ],
                'trigger' => 'trial_expired',
                'variables' => ['owner_name', 'clinic_name', 'login_url'],
            ],
            [
                'code' => 'payment_success',
                'name' => 'Payment Successful',
                'subject' => [
                    'en' => 'Payment received - Invoice #{invoice_number}',
                    'ar' => 'تم استلام الدفعة - فاتورة #{invoice_number}',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Thank you! We received your payment of <strong>{invoice_amount}</strong> for {clinic_name}.</p><p>View your invoice: <a href="{invoice_url}">{invoice_url}</a></p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>شكراً لك! استلمنا دفعتك بقيمة <strong>{invoice_amount}</strong> لـ {clinic_name}.</p><p>اعرض فاتورتك: <a href="{invoice_url}">{invoice_url}</a></p>',
                ],
                'trigger' => 'payment_success',
                'variables' => ['owner_name', 'clinic_name', 'invoice_amount', 'invoice_number', 'invoice_url'],
            ],
            [
                'code' => 'payment_failed',
                'name' => 'Payment Failed',
                'subject' => [
                    'en' => 'Payment failed - Action required',
                    'ar' => 'فشل الدفع - يتطلب إجراء',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>We were unable to process your payment of <strong>{invoice_amount}</strong> for {clinic_name}.</p><p>Please update your payment method to avoid service interruption.</p><p><a href="{login_url}">Update Payment Method</a></p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>لم نتمكن من معالجة دفعتك بقيمة <strong>{invoice_amount}</strong> لـ {clinic_name}.</p><p>يرجى تحديث طريقة الدفع لتجنب انقطاع الخدمة.</p>',
                ],
                'trigger' => 'payment_failed',
                'variables' => ['owner_name', 'clinic_name', 'invoice_amount', 'login_url'],
            ],
            [
                'code' => 'account_suspended',
                'name' => 'Account Suspended',
                'subject' => [
                    'en' => 'Your account has been suspended',
                    'ar' => 'تم تعليق حسابك',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Your account for <strong>{clinic_name}</strong> has been suspended due to non-payment.</p><p>Please settle your outstanding balance to restore access.</p><p>Contact us at {support_email} if you need help.</p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>تم تعليق حسابك لـ <strong>{clinic_name}</strong> بسبب عدم الدفع.</p><p>يرجى تسوية رصيدك المستحق لاستعادة الوصول.</p>',
                ],
                'trigger' => 'account_suspended',
                'variables' => ['owner_name', 'clinic_name', 'support_email'],
            ],
            [
                'code' => 'plan_upgraded',
                'name' => 'Plan Upgraded',
                'subject' => [
                    'en' => 'Welcome to {plan_name}!',
                    'ar' => 'مرحباً بك في {plan_name}!',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Great news! Your <strong>{clinic_name}</strong> has been upgraded to the <strong>{plan_name}</strong> plan.</p><p>You now have access to more features and higher limits.</p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>أخبار رائعة! تمت ترقية <strong>{clinic_name}</strong> إلى خطة <strong>{plan_name}</strong>.</p><p>أصبح لديك الآن المزيد من الميزات وحدود أعلى.</p>',
                ],
                'trigger' => 'plan_upgraded',
                'variables' => ['owner_name', 'clinic_name', 'plan_name'],
            ],
            [
                'code' => 'quota_warning',
                'name' => 'Quota Warning',
                'subject' => [
                    'en' => 'You\'re approaching your {resource} limit',
                    'ar' => 'أنت تقترب من حد {resource} الخاص بك',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Your <strong>{clinic_name}</strong> has used {usage_percentage}% of your {resource} quota.</p><p>Consider upgrading your plan to get more capacity.</p><p>{usage_summary}</p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>استخدم <strong>{clinic_name}</strong> {usage_percentage}% من حصة {resource} الخاصة بك.</p><p>فكر في ترقية خطتك للحصول على سعة أكبر.</p>',
                ],
                'trigger' => 'quota_warning',
                'variables' => ['owner_name', 'clinic_name', 'resource', 'usage_percentage', 'usage_summary'],
            ],
            [
                'code' => 'password_reset',
                'name' => 'Password Reset',
                'subject' => [
                    'en' => 'Reset your password',
                    'ar' => 'إعادة تعيين كلمة المرور',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Click the link below to reset your password:</p><p><a href="{reset_url}">{reset_url}</a></p><p>This link expires in 60 minutes.</p><p>If you didn\'t request this, please ignore this email.</p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>انقر على الرابط أدناه لإعادة تعيين كلمة المرور:</p><p><a href="{reset_url}">{reset_url}</a></p><p>ينتهي هذا الرابط خلال 60 دقيقة.</p>',
                ],
                'trigger' => 'password_reset',
                'variables' => ['owner_name', 'reset_url'],
            ],
            [
                'code' => 'invoice_generated',
                'name' => 'Invoice Generated',
                'subject' => [
                    'en' => 'Invoice #{invoice_number} for {clinic_name}',
                    'ar' => 'فاتورة #{invoice_number} لـ {clinic_name}',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Your invoice for <strong>{clinic_name}</strong> is ready.</p><p>Amount: <strong>{invoice_amount}</strong></p><p>Due date: {due_date}</p><p><a href="{invoice_url}">View Invoice</a></p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>فاتورتك لـ <strong>{clinic_name}</strong> جاهزة.</p><p>المبلغ: <strong>{invoice_amount}</strong></p><p>تاريخ الاستحقاق: {due_date}</p>',
                ],
                'trigger' => 'invoice_generated',
                'variables' => ['owner_name', 'clinic_name', 'invoice_number', 'invoice_amount', 'due_date', 'invoice_url'],
            ],
            [
                'code' => 'account_reactivated',
                'name' => 'Account Reactivated',
                'subject' => [
                    'en' => 'Your account has been reactivated!',
                    'ar' => 'تم إعادة تفعيل حسابك!',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Great news! Your account for <strong>{clinic_name}</strong> has been reactivated.</p><p>You now have full access to all your data and features.</p><p><a href="{login_url}">Login Now</a></p><p>Thank you for staying with us!</p><p>Best regards,<br>{platform_name} Team</p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>أخبار رائعة! تم إعادة تفعيل حسابك لـ <strong>{clinic_name}</strong>.</p><p>لديك الآن وصول كامل إلى جميع بياناتك وميزاتك.</p><p><a href="{login_url}">تسجيل الدخول الآن</a></p><p>شكراً لبقائك معنا!</p><p>مع أطيب التحيات،<br>فريق {platform_name}</p>',
                ],
                'trigger' => 'account_reactivated',
                'variables' => ['owner_name', 'clinic_name', 'login_url', 'platform_name'],
            ],
            [
                'code' => 'plan_downgraded',
                'name' => 'Plan Downgraded',
                'subject' => [
                    'en' => 'Your plan has been changed to {plan_name}',
                    'ar' => 'تم تغيير خطتك إلى {plan_name}',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Your <strong>{clinic_name}</strong> subscription has been changed to the <strong>{plan_name}</strong> plan.</p><p>Please note that some features may no longer be available with your new plan.</p><p>If this was a mistake or you\'d like to upgrade again, please visit your billing page.</p><p><a href="{login_url}">Manage Subscription</a></p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>تم تغيير اشتراك <strong>{clinic_name}</strong> إلى خطة <strong>{plan_name}</strong>.</p><p>يرجى ملاحظة أن بعض الميزات قد لا تكون متاحة مع خطتك الجديدة.</p><p>إذا كان هذا خطأ أو ترغب في الترقية مرة أخرى، يرجى زيارة صفحة الفوترة الخاصة بك.</p>',
                ],
                'trigger' => 'plan_downgraded',
                'variables' => ['owner_name', 'clinic_name', 'plan_name', 'login_url'],
            ],
            [
                'code' => 'monthly_usage_report',
                'name' => 'Monthly Usage Report',
                'subject' => [
                    'en' => 'Your monthly usage report for {month_year}',
                    'ar' => 'تقرير الاستخدام الشهري لـ {month_year}',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>Here\'s your monthly usage summary for <strong>{clinic_name}</strong>:</p><h3>Usage Summary - {month_year}</h3><ul><li>Appointments: {appointments_count}</li><li>New Patients: {new_patients_count}</li><li>WhatsApp Messages: {whatsapp_count}</li><li>SMS Messages: {sms_count}</li><li>Storage Used: {storage_used}</li></ul><p><a href="{dashboard_url}">View Full Report</a></p><p>Best regards,<br>{platform_name} Team</p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>إليك ملخص الاستخدام الشهري لـ <strong>{clinic_name}</strong>:</p><h3>ملخص الاستخدام - {month_year}</h3><ul><li>المواعيد: {appointments_count}</li><li>المرضى الجدد: {new_patients_count}</li><li>رسائل واتساب: {whatsapp_count}</li><li>رسائل SMS: {sms_count}</li><li>التخزين المستخدم: {storage_used}</li></ul><p><a href="{dashboard_url}">عرض التقرير الكامل</a></p><p>مع أطيب التحيات،<br>فريق {platform_name}</p>',
                ],
                'trigger' => 'monthly_report',
                'variables' => ['owner_name', 'clinic_name', 'month_year', 'appointments_count', 'new_patients_count', 'whatsapp_count', 'sms_count', 'storage_used', 'dashboard_url', 'platform_name'],
            ],
            [
                'code' => 'new_feature_announcement',
                'name' => 'New Feature Announcement',
                'subject' => [
                    'en' => 'New Feature: {feature_name}',
                    'ar' => 'ميزة جديدة: {feature_name}',
                ],
                'body' => [
                    'en' => '<p>Hi {owner_name},</p><p>We\'re excited to announce a new feature for {platform_name}!</p><h3>{feature_name}</h3><p>{feature_description}</p><p><a href="{feature_url}">Try it now</a></p><p>As always, we\'d love to hear your feedback!</p><p>Best regards,<br>{platform_name} Team</p>',
                    'ar' => '<p>مرحباً {owner_name}،</p><p>يسعدنا الإعلان عن ميزة جديدة في {platform_name}!</p><h3>{feature_name}</h3><p>{feature_description}</p><p><a href="{feature_url}">جربها الآن</a></p><p>كالعادة، نود سماع ملاحظاتك!</p><p>مع أطيب التحيات،<br>فريق {platform_name}</p>',
                ],
                'trigger' => 'new_feature',
                'variables' => ['owner_name', 'platform_name', 'feature_name', 'feature_description', 'feature_url'],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['code' => $template['code']],
                $template
            );
        }
    }
}
