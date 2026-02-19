<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class EmailTemplate extends Model
{
    use HasUuids, HasTranslations;

    protected $fillable = [
        'code',
        'name',
        'subject',
        'body',
        'trigger',
        'is_active',
        'variables',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'variables' => 'array',
    ];

    public array $translatable = ['subject', 'body'];

    public const TRIGGERS = [
        'on_signup' => 'On Signup',
        'trial_expiring' => 'Trial Expiring (3 days)',
        'trial_expired' => 'Trial Expired',
        'payment_success' => 'Payment Success',
        'payment_failed' => 'Payment Failed',
        'account_suspended' => 'Account Suspended',
        'account_reactivated' => 'Account Reactivated',
        'plan_upgraded' => 'Plan Upgraded',
        'plan_downgraded' => 'Plan Downgraded',
        'monthly_report' => 'Monthly Usage Report',
        'quota_warning' => 'Quota Warning (80%)',
        'announcement' => 'Announcement',
        'password_reset' => 'Password Reset',
        'invoice_generated' => 'Invoice Generated',
        'manual' => 'Manual Send',
    ];

    public const AVAILABLE_VARIABLES = [
        'clinic_name' => 'The clinic/tenant name',
        'owner_name' => 'The owner\'s full name',
        'owner_email' => 'The owner\'s email',
        'plan_name' => 'Current subscription plan name',
        'trial_end_date' => 'Trial expiration date',
        'invoice_amount' => 'Invoice total amount',
        'invoice_url' => 'Link to view/pay invoice',
        'login_url' => 'Direct login URL',
        'usage_summary' => 'Usage stats summary',
        'platform_name' => 'Platform name (XLinic)',
        'support_email' => 'Support email address',
    ];

    public function scopeActive($query)
    {
        return $query->whereRaw('is_active = true');
    }

    public function scopeByTrigger($query, string $trigger)
    {
        return $query->where('trigger', $trigger);
    }

    public function render(array $data, string $locale = 'en'): array
    {
        $subject = $this->getTranslation('subject', $locale);
        $body = $this->getTranslation('body', $locale);

        foreach ($data as $key => $value) {
            $subject = str_replace('{' . $key . '}', $value, $subject);
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    public static function getSampleData(string $locale = 'en'): array
    {
        $isArabic = $locale === 'ar';

        return [
            'clinic_name' => $isArabic ? 'عيادة كايرو جلو' : 'Cairo Glow Clinic',
            'owner_name' => $isArabic ? 'د. سارة أحمد' : 'Dr. Sarah Ahmed',
            'owner_email' => 'sarah@cairoglow.com',
            'patient_name' => $isArabic ? 'أحمد حسن' : 'Ahmed Hassan',
            'patient_email' => 'ahmed@example.com',
            'plan_name' => $isArabic ? 'احترافي' : 'Professional',
            'trial_end_date' => $isArabic ? '25 فبراير 2026' : 'Feb 25, 2026',
            'appointment_date' => $isArabic ? '25 فبراير 2026' : 'Feb 25, 2026',
            'appointment_time' => $isArabic ? '10:30 صباحاً' : '10:30 AM',
            'service_name' => $isArabic ? 'إزالة الشعر بالليزر' : 'Laser Hair Removal',
            'doctor_name' => $isArabic ? 'د. سارة أحمد' : 'Dr. Sarah Ahmed',
            'invoice_amount' => $isArabic ? '1,500.00 ج.م' : 'EGP 1,500.00',
            'invoice_number' => 'INV-2026-0001',
            'invoice_url' => 'https://app.xlinic.com/invoices/xxx',
            'login_url' => 'https://cairo-glow.xlinic.com/admin',
            'booking_link' => 'https://cairo-glow.xlinic.com/book',
            'usage_summary' => $isArabic ? '85 مريض، 12 مستخدم، 500MB تخزين' : '85 patients, 12 users, 500MB storage',
            'platform_name' => 'XLinic',
            'support_email' => 'support@xlinic.com',
            'amount' => $isArabic ? '1,500.00 ج.م' : 'EGP 1,500.00',
            'reset_link' => 'https://app.xlinic.com/reset-password/xxx',
        ];
    }

    public function renderSubjectPreview(string $locale = 'en'): string
    {
        $subject = $this->getTranslation('subject', $locale) ?? '';
        $sampleData = self::getSampleData($locale);

        foreach ($sampleData as $key => $value) {
            $subject = str_replace('{' . $key . '}', $value, $subject);
        }

        return $subject;
    }

    public function renderBodyPreview(string $locale = 'en'): string
    {
        $body = $this->getTranslation('body', $locale) ?? '';
        $sampleData = self::getSampleData($locale);

        foreach ($sampleData as $key => $value) {
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        return $body;
    }
}
