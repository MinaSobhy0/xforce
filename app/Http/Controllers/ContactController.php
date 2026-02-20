<?php

namespace App\Http\Controllers;

use App\Models\ContactInquiry;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        $platformName = PlatformSetting::get('platform_name', 'XLinic');
        $websiteLogo = PlatformSetting::get('website_logo') ?: PlatformSetting::get('platform_logo');
        $primaryColor = PlatformSetting::get('primary_color', '#3b82f6');
        $footerText = PlatformSetting::get('footer_text', '© ' . date('Y') . ' XLinic. All rights reserved.');
        $supportEmail = PlatformSetting::get('support_email', 'support@xlinic.com');
        $recaptchaEnabled = PlatformSetting::get('recaptcha_enabled', false);
        $recaptchaSiteKey = PlatformSetting::get('recaptcha_site_key', '');

        return view('contact', compact(
            'platformName',
            'websiteLogo',
            'primaryColor',
            'footerText',
            'supportEmail',
            'recaptchaEnabled',
            'recaptchaSiteKey'
        ));
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'clinic_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'country' => 'required|string|max:2',
            'message' => 'nullable|string|max:2000',
            'g-recaptcha-response' => $this->isRecaptchaEnabled() ? 'required' : 'nullable',
        ]);

        // Verify reCAPTCHA
        if ($this->isRecaptchaEnabled()) {
            $recaptchaSecret = PlatformSetting::get('recaptcha_secret_key');
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $recaptchaSecret,
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]);

            if (!$response->json('success')) {
                return back()
                    ->withInput()
                    ->withErrors(['recaptcha' => 'reCAPTCHA verification failed. Please try again.']);
            }
        }

        // Create inquiry
        $inquiry = ContactInquiry::create([
            'clinic_name' => $validated['clinic_name'],
            'contact_name' => $validated['contact_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'country' => $validated['country'],
            'message' => $validated['message'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Send notification email
        $this->sendNotificationEmail($inquiry);

        return back()->with('success', 'Thank you for your inquiry! We will contact you soon.');
    }

    protected function isRecaptchaEnabled(): bool
    {
        return (bool) PlatformSetting::get('recaptcha_enabled', false);
    }

    protected function sendNotificationEmail(ContactInquiry $inquiry): void
    {
        $supportEmail = PlatformSetting::get('support_email', 'support@xlinic.com');
        $platformName = PlatformSetting::get('platform_name', 'XLinic');

        try {
            Mail::send([], [], function ($message) use ($inquiry, $supportEmail, $platformName) {
                $message->to($supportEmail)
                    ->subject("New Contact Inquiry from {$inquiry->clinic_name}")
                    ->html($this->buildEmailContent($inquiry, $platformName));
            });
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Failed to send contact inquiry notification: ' . $e->getMessage());
        }
    }

    protected function buildEmailContent(ContactInquiry $inquiry, string $platformName): string
    {
        $countries = [
            'EG' => 'Egypt',
            'SA' => 'Saudi Arabia',
            'AE' => 'UAE',
            'KW' => 'Kuwait',
            'QA' => 'Qatar',
            'BH' => 'Bahrain',
            'OM' => 'Oman',
            'JO' => 'Jordan',
            'LB' => 'Lebanon',
        ];

        $countryName = $countries[$inquiry->country] ?? $inquiry->country;

        return "
            <h2>New Contact Inquiry</h2>
            <p>A new inquiry has been submitted on {$platformName}.</p>
            <hr>
            <p><strong>Clinic Name:</strong> {$inquiry->clinic_name}</p>
            <p><strong>Contact Name:</strong> {$inquiry->contact_name}</p>
            <p><strong>Email:</strong> <a href='mailto:{$inquiry->email}'>{$inquiry->email}</a></p>
            <p><strong>Phone:</strong> {$inquiry->phone}</p>
            <p><strong>Country:</strong> {$countryName}</p>
            <p><strong>Message:</strong></p>
            <p>{$inquiry->message}</p>
            <hr>
            <p><small>Submitted at: {$inquiry->created_at->format('Y-m-d H:i:s')}</small></p>
            <p><small>IP Address: {$inquiry->ip_address}</small></p>
        ";
    }
}
