@component('mail::message')
# Tenant User Limit Exceeded

A tenant has exceeded their user limit and needs to purchase additional users.

## Tenant Details

| Field | Value |
|-------|-------|
| **Tenant Name** | {{ $tenant->name }} |
| **Tenant ID** | {{ $tenant->id }} |
| **Contact Email** | {{ $tenant->contact_email ?? 'N/A' }} |
| **Contact Phone** | {{ $tenant->contact_phone ?? 'N/A' }} |

## Usage Details

| Metric | Value |
|--------|-------|
| **Current Users** | {{ $currentCount }} |
| **User Limit** | {{ $limit }} |
| **Over By** | {{ $overage }} user(s) |
| **Grace Period Ends** | {{ $gracePeriodEnds?->format('M d, Y') ?? 'N/A' }} |

## Plan Information

| Field | Value |
|-------|-------|
| **Base Limit** | {{ $tenant->subscriptionPlan?->max_users ?? $tenant->max_users ?? 'N/A' }} |
| **Extra Users Purchased** | {{ $tenant->extra_users ?? 0 }} |
| **Effective Limit** | {{ $limit }} |

The tenant has been notified and given a 14-day grace period to purchase additional users.

@component('mail::button', ['url' => config('app.url') . '/platform/tenants/' . $tenant->id])
View Tenant in Platform
@endcomponent

Thanks,<br>
{{ config('app.name') }} System
@endcomponent
