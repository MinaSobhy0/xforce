<x-mail::message>
# Hello {{ $request->owner_name }},

Thank you for your interest in XLinic for **{{ $request->clinic_name }}**.

After careful review of your application, we regret to inform you that we are unable to proceed with your account setup at this time.

**Reason:**
{{ $reason }}

If you believe this was made in error, or if you would like to provide additional information, please don't hesitate to contact our support team.

We appreciate your understanding and wish you the best in your healthcare management endeavors.

<x-mail::button :url="config('app.url')">
Visit XLinic
</x-mail::button>

Best regards,<br>
The {{ config('app.name') }} Team
</x-mail::message>
