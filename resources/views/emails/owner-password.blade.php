@component('mail::message')
# Your Owner Portal Access

Hello {{ $user->first_name }},

Your password for the XLinic Owner Portal has been set.

**Login Details:**

| Field | Value |
|-------|-------|
| **Email** | {{ $user->email }} |
| **Password** | {{ $password }} |

@component('mail::button', ['url' => $loginUrl])
Login to Owner Portal
@endcomponent

Please change your password after logging in for security.

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
