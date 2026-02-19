<x-mail::message>
# Welcome to XLinic, {{ $request->owner_name }}!

Great news! Your account for **{{ $request->clinic_name }}** has been set up and is ready to use.

## Your Login Details

<x-mail::panel>
**Login URL:** [{{ $loginUrl }}]({{ $loginUrl }})

**Email:** {{ $request->owner_email }}

@if($temporaryPassword)
**Temporary Password:** {{ $temporaryPassword }}

*Please change your password after your first login.*
@else
*Use the password you provided during registration.*
@endif
</x-mail::panel>

## Getting Started

Here are a few things you can do to get started:

1. **Complete Your Profile** - Add your clinic's logo and contact information
2. **Add Your Team** - Invite staff members to join your clinic
3. **Set Up Services** - Configure your treatments and pricing
4. **Import Patients** - Bring your existing patient data into XLinic

<x-mail::button :url="$loginUrl">
Login to Your Clinic
</x-mail::button>

## Need Help?

Our support team is here to help you get the most out of XLinic:
- **Email:** support@xlinic.com
- **Documentation:** [docs.xlinic.com](https://docs.xlinic.com)

Thank you for choosing XLinic for your clinic management needs!

Best regards,<br>
The {{ config('app.name') }} Team
</x-mail::message>
