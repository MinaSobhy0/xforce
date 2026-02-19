<x-mail::message>
# Hello {{ $request->owner_name }},

We're reviewing your application for **{{ $request->clinic_name }}** and need a bit more information to proceed.

{!! $messageContent !!}

Please reply to this email with the requested information, and we'll continue processing your application promptly.

<x-mail::panel>
**Application Details:**
- Clinic Name: {{ $request->clinic_name }}
- Subdomain: {{ $request->slug }}.xlinic.com
- Plan: {{ $request->plan?->code ?? 'Not specified' }}
</x-mail::panel>

If you have any questions, please don't hesitate to reach out.

Best regards,<br>
The {{ config('app.name') }} Team
</x-mail::message>
