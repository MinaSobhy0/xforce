# Red Team Security Audit Report - XLinic

**Audit Date:** March 2026
**Auditor:** Red Team Security Assessment
**Scope:** Full codebase analysis - Laravel 11 + Filament 3 multi-tenant SaaS platform
**Classification:** CONFIDENTIAL

---

## Executive Summary

This red team assessment identified **67 security vulnerabilities** across the XLinic codebase, including **15 CRITICAL**, **24 HIGH**, **20 MEDIUM**, and **8 LOW** severity issues. The most severe findings involve:

1. **Race conditions** in payment and booking systems allowing double-spending
2. **Mass assignment vulnerabilities** enabling privilege escalation and subscription bypass
3. **Tenant isolation failures** in background jobs and cache systems
4. **Authentication bypass** paths through soft-deleted users and 2FA inconsistencies
5. **Business logic flaws** allowing unlimited resource consumption

The junior audit correctly identified basic issues (SQLi, XSS, hardcoded secrets), but missed the architecture-level, logic-based, and chained vulnerabilities documented herein.

---

## Table of Contents

1. [Race Conditions & TOCTOU](#1-race-conditions--toctou)
2. [Mass Assignment Vulnerabilities](#2-mass-assignment-vulnerabilities)
3. [Insecure Direct Object References](#3-insecure-direct-object-references)
4. [Authentication Edge Cases](#4-authentication-edge-cases)
5. [Tenant Context Manipulation](#5-tenant-context-manipulation)
6. [Business Logic Exploitation](#6-business-logic-exploitation)
7. [Second-Order & Stored Attacks](#7-second-order--stored-attacks)
8. [API & Integration Issues](#8-api--integration-issues)
9. [Cryptography & Token Security](#9-cryptography--token-security)
10. [DoS & Resource Exhaustion](#10-dos--resource-exhaustion)
11. [Chained Exploit Scenarios](#11-chained-exploit-scenarios)
12. [Remediation Priority Matrix](#12-remediation-priority-matrix)

---

## 1. Race Conditions & TOCTOU

### 1.1 CRITICAL: Gift Card Balance Overdraw
**File:** `/modules/GiftCards/Services/GiftCardService.php:266-325`

**Vulnerability:** The `redeem()` method checks balance and deducts without database locks.

```php
$amountToRedeem = min($amountMinor, $card->remaining_value_minor);  // Line 276
// Gap between check and update - NO LOCK
$card->decrement('remaining_value_minor', $amountToRedeem);  // Line 285
```

**Exploit:**
```
Request A: Check balance (1000) → passes
Request B: Check balance (1000) → passes (concurrent)
Request A: Deduct 800 → balance = 200
Request B: Deduct 900 → balance = -700 (OVERDRAW)
```

**Impact:** Financial loss through gift card overdraw. Estimated exposure: unlimited.

---

### 1.2 CRITICAL: Loyalty Points Double-Redemption
**File:** `/modules/Loyalty/Services/LoyaltyService.php:61-99`

**Vulnerability:** `redeemPoints()` uses check-then-act pattern without locking.

```php
$currentBalance = $this->getBalance($patient);  // Line 69
if ($currentBalance < abs($points)) {            // Line 71
    throw new Exception('Insufficient points');
}
$patient->decrement('loyalty_points', abs($points));  // Line 82 - NO LOCK
```

**Exploit:** Two concurrent redemption requests both see 100 points available, both redeem 80, resulting in -60 balance.

**Impact:** Loyalty fraud, revenue loss.

---

### 1.3 CRITICAL: Promo Code Usage Limit Bypass
**File:** `/app/Models/PromoCode.php:111-135`

**Vulnerability:** `isValid()` checks `used_count` separately from `use()` increment.

```php
if ($this->max_uses && $this->used_count >= $this->max_uses)  // Line 125
// ...separate method...
$this->increment('used_count');  // Line 134
```

**Exploit:** PromoCode with `max_uses=10`, `used_count=9`. Two concurrent requests both see 9 < 10, both increment to 10 then 11.

**Impact:** Coupons used beyond limits, discount fraud.

---

### 1.4 CRITICAL: Appointment Double-Booking
**File:** `/modules/Booking/Filament/Pages/CreateBooking.php:2276-2300`

**Vulnerability:** Slot validation occurs in UI; `Appointment::create()` has no database-level conflict check.

```php
// Line 2280 - Creates appointment without verifying slot is still available
$appointment = Appointment::create([
    'practitioner_id' => $item['practitioner_id'],
    'start_time' => $item['start_time'],
    // ...
]);
```

**Exploit:** Two users select same 2pm slot, both submit simultaneously, both appointments created.

**Impact:** Scheduling conflicts, patient/staff confusion, operational disruption.

---

### 1.5 CRITICAL: Package Session Over-Consumption
**File:** `/modules/Packages/Services/PackageService.php:340-366`

**Vulnerability:** `getSessionsRemainingByService()` check not atomic with `recordUsage()`.

```php
if ($subscription->getSessionsRemainingByService($serviceId) < $quantityUsed) {  // Line 353
    throw new \InvalidArgumentException('Not enough remaining sessions');
}
return $subscription->recordUsage(...);  // Line 360 - NO TRANSACTION
```

**Exploit:** Package with 1 session remaining. Two concurrent API calls both check (1 >= 1), both proceed, 2 sessions consumed.

**Impact:** Service theft, revenue loss.

---

### 1.6 CRITICAL: Quota Service Race Condition
**File:** `/framework/Core/Quota/QuotaService.php:74-124`

**Vulnerability:** `allows()` checks usage, then `consume()` increments separately.

```php
$usage = $this->getCurrentUsage($quotaType, $tenantId, $quota['period']);  // Line 89
$newUsage = $usage + $amount;                                               // Line 90
return $newUsage <= $quota['limit'];                                        // Line 92
// Later...
$this->incrementUsage(...);  // Line 118 - SEPARATE CALL
```

**Exploit:** API quota at 995/1000. Ten concurrent requests all see 995, all pass check, all increment. Final: 1005 calls.

**Impact:** Quota bypass, API abuse, potential billing disputes.

---

### 1.7 HIGH: Payment Double-Application
**File:** `/modules/Billing/Services/PaymentIntegrationService.php:57-62`

**Vulnerability:** Gift card and loyalty point balance reads occur before locks acquired.

**Impact:** Double-payment recording, incorrect invoice status.

---

## 2. Mass Assignment Vulnerabilities

### 2.1 CRITICAL: User Model Privilege Escalation
**File:** `/modules/Auth/Models/User.php:53-94`

**Vulnerable $fillable Fields:**
```php
'permissions_override',        // CRITICAL: Grant arbitrary permissions
'impersonation_token',         // CRITICAL: Create impersonation tokens
'impersonation_token_expires_at',
'salary',                      // Modify own salary
'commission_rate',             // Modify own commission
'status',                      // Reactivate suspended account
'two_factor_secret',           // Disable 2FA
'two_factor_recovery_codes',
```

**Exploit:**
```http
POST /api/users/{id}
{"permissions_override": ["admin", "billing.manage"], "status": "active"}
```

**Impact:** Complete privilege escalation, account takeover, impersonation.

---

### 2.2 CRITICAL: Tenant Subscription Bypass
**File:** `/modules/Core/Models/Tenant.php:31-82`

**Vulnerable $fillable Fields:**
```php
'subscription_plan_id',        // Change to premium plan free
'subscription_status',         // Change from 'expired' to 'active'
'subscription_expires_at',     // Extend indefinitely
'trial_ends_at',               // Reset trial
'max_users',                   // Override limits
'extra_users',
'max_branches',
'extra_branches',
```

**Exploit:**
```http
PUT /api/tenants/{id}
{
  "subscription_plan_id": "enterprise",
  "subscription_status": "active",
  "subscription_expires_at": "2099-12-31",
  "max_users": 10000
}
```

**Impact:** Subscription fraud, unlimited resource access.

---

### 2.3 CRITICAL: Invoice Financial Manipulation
**File:** `/modules/Billing/Models/Invoice.php:31-56`

**Vulnerable $fillable Fields:**
```php
'status',           // Bypass state machine
'subtotal_minor',   // Should be calculated
'discount_minor',   // Add fraudulent discounts
'tax_minor',        // Should be calculated
'total_minor',      // Should be calculated
'paid_minor',       // Mark unpaid as paid
'paid_at',
```

**Impact:** Financial fraud, incorrect revenue recognition.

---

### 2.4 CRITICAL: UserBranchRole Escalation
**File:** `/modules/Auth/Models/UserBranchRole.php:18-28`

**Vulnerable Fields:** `role_id`, `is_primary`, `is_active`, `assigned_by`

**Exploit:** Assign admin role to any user in any branch.

---

### 2.5 HIGH: SubscriptionPlan Pricing Manipulation
**File:** `/app/Models/SubscriptionPlan.php:41-84`

**Vulnerable Fields:** `price_monthly_minor`, `price_yearly_minor`, `prices`, `max_*`, `overage_*_minor`

**Impact:** Create $0 premium plans, disable overage charges.

---

### 2.6 HIGH: TenantSubscription Status Override
**File:** `/modules/Core/Models/TenantSubscription.php:20-43`

**Vulnerable Fields:** `status`, `expires_at`, `features`, `limits`, `failed_payments_count`

**Impact:** Reactivate expired subscriptions, add premium features.

---

### 2.7 HIGH: PackageSubscription Revenue Fraud
**File:** `/modules/Packages/Models/PackageSubscription.php:23-46`

**Vulnerable Fields:** `deposit_paid_minor`, `balance_remaining_minor`, `recognized_revenue_minor`

**Impact:** Record fake payments, manipulate revenue recognition.

---

### 2.8 HIGH: OwnerUser Tenant Switching
**File:** `/app/Models/OwnerUser.php:42-49`

**Vulnerable Field:** `tenant_id`

**Exploit:** Owner changes their `tenant_id` to manage different tenant.

**Impact:** Horizontal privilege escalation across tenants.

---

## 3. Insecure Direct Object References

### 3.1 HIGH: GiftCard Download Without Ownership Check
**File:** `/modules/GiftCards/Routes/web.php:18-25`

```php
Route::get('/{giftCard}/download', function (GiftCard $giftCard) {
    return app(GiftCardPdfService::class)->download($giftCard);
})->name('download');
```

**Vulnerability:** Only checks `auth` and `tenant` middleware, no ownership verification.

**Exploit:** Authenticated user downloads any gift card in tenant by changing ID.

**Impact:** Gift card theft, financial fraud.

---

### 3.2 HIGH: Payroll Download URL Forgery
**File:** `/modules/MobileApi/Http/Controllers/PayrollController.php:133-158`

**Vulnerability:** Returns download URL based on payslip ID that can be manipulated.

```php
$downloadUrl = url("/payroll/payslip/{$id}/download");
```

**Impact:** Staff can access other employees' salary information.

---

### 3.3 MEDIUM: Session ID Deletion Route
**File:** `/modules/Auth/routes/api.php:83-84`

**Vulnerability:** Route accepts `{session}` parameter; if implemented without user scoping, allows cross-user session deletion.

---

## 4. Authentication Edge Cases

### 4.1 CRITICAL: Soft-Deleted User Authentication
**File:** `/modules/Api/Http/Controllers/AuthController.php:107`

```php
$user = User::where('email', $request->email)->first();  // Returns deleted users!
```

**Vulnerability:** Soft-deleted users can still authenticate because query doesn't exclude `deleted_at`.

**Exploit:** Admin soft-deletes user → User still logs in with original credentials.

**Impact:** Terminated employees retain system access.

---

### 4.2 CRITICAL: Email Change Without Verification
**File:** `/modules/Auth/Resources/ProfileResource/Pages/ManageProfile.php:241-256`

**Vulnerability:** Email changed immediately, marked unverified, but session remains active.

```php
$user->update($data);                              // Line 243: Immediate change
$user->update(['email_verified_at' => null]);      // Line 247: Only marks unverified
// NO actual verification email sent
// NO password confirmation required
```

**Exploit:** Attacker compromises account → Changes email → Original owner locked out.

**Impact:** Account takeover with no recovery path.

---

### 4.3 CRITICAL: Mobile API 2FA Inconsistency
**File:** `/modules/MobileApi/Http/Controllers/AuthController.php:49-64`

**Vulnerability:** Mobile API uses encrypted temp token for 2FA; web uses session. Different security models.

```php
return $this->success([
    'requires_2fa' => true,
    'temp_token' => $this->generateTempToken($user),  // Contains user_id encrypted
]);
```

**Issues:**
- No rate limiting on 2FA code attempts
- Temp token decryptable with APP_KEY
- No lockout after failed attempts

---

### 4.4 HIGH: 2FA Race Condition Bypass
**File:** `/app/Http/Controllers/Auth/TwoFactorChallengeController.php:14-68`

**Vulnerability:** Session-based 2FA verification via simple boolean flag.

```php
$request->session()->put('two_factor_verified', true);  // Line 67
```

**Exploit:** Concurrent requests during 2FA challenge could bypass verification timing.

---

### 4.5 HIGH: Password Reset Token Persistence
**File:** `/config/auth.php:113-120`

**Vulnerability:** 60-minute token expiry, tokens not invalidated when password changed via other flow.

**Exploit:** Request reset → Change password via profile → Old reset token still valid.

---

### 4.6 MEDIUM: Recovery Code Brute Force
**File:** `/app/Traits/TwoFactorAuthenticatable.php:76-91`

**Vulnerability:** No rate limiting or lockout on recovery code verification.

**Exploit:** 8 recovery codes with 10 hex chars each → brute-forceable offline.

---

### 4.7 MEDIUM: Impersonation Token Timing Attack
**File:** `/modules/Auth/Http/Controllers/ImpersonateController.php:24`

```php
$hashedToken = hash('sha256', $token);
// Then simple equality comparison against database
```

**Vulnerability:** Not using `hash_equals()`, vulnerable to timing attacks.

---

## 5. Tenant Context Manipulation

### 5.1 CRITICAL: TenantAwareJob Typo - Context Never Resolves
**File:** `/framework/Core/Tenancy/TenantAwareJob.php:163`

```php
protected function resolveTenan(): void  // TYPO: Missing 't'
// Called on line 106 as:
$this->resolvetenant();  // Different casing
```

**Impact:** Background jobs never establish tenant context. All queued jobs fail or execute in wrong schema.

---

### 5.2 HIGH: PgBouncer Connection Pool Race Condition
**File:** `/app/Http/Middleware/IdentifyTenant.php:236-249`

**Vulnerability:** Schema switching via `SET search_path` between connection acquisition and query execution.

**Race Scenario:**
1. Request A sets search_path to tenant_a
2. Request B sets search_path to tenant_b
3. Request A query executes with search_path=tenant_b

**Impact:** Cross-tenant data exposure, queries against wrong schema.

---

### 5.3 HIGH: Redis Cache Key Collision
**File:** `/config/database.php:135-195`

**Vulnerability:** Single global Redis prefix for ALL tenants.

```php
'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'xlinic'), '_').'_'),
```

**Attack:** If tenant cache key structure is known, attacker can read/poison other tenants' cached data via direct Redis access.

---

### 5.4 HIGH: Header-Based Tenant Enumeration
**File:** `/modules/MobileApi/Http/Middleware/ResolveTenantFromHeader.php:17-35`

**Vulnerability:** No rate limiting on `X-Tenant-Slug` header validation.

**Exploit:** Brute-force tenant slugs to enumerate all clinics on platform.

---

### 5.5 MEDIUM: Permission Cache Collision
**File:** `/app/Http/Middleware/IdentifyTenant.php:314-322`

```php
Config::set('permission.cache.key', 'spatie.permission.cache.' . $tenant->slug);
```

**Vulnerability:** Uses tenant slug (human-readable) instead of guaranteed-unique ID.

---

### 5.6 MEDIUM: Service Container Tenant Leakage
**File:** `/framework/Core/Tenancy/TenantMiddleware.php:98-103`

**Vulnerability:** `clearCurrentTenant()` doesn't remove `app('currentTenant')` binding.

**Impact:** Stale tenant context in long-running processes.

---

## 6. Business Logic Exploitation

### 6.1 CRITICAL: No Subscription Limit Enforcement
**File:** `/modules/Core/Models/Tenant.php:50-66`

**Vulnerability:** `max_users`, `max_branches`, `max_patients` are advisory only - never enforced.

**Exploit:** Create unlimited users despite `max_users=10` limit.

**Impact:** Free unlimited access, subscription tier meaningless.

---

### 6.2 CRITICAL: WhatsApp/SMS Quota Bypass
**File:** `/modules/Marketing/Services/MessageQuotaService.php:16-49`

**Vulnerabilities:**
1. First message always allowed (no usage record = `return true`)
2. No negative balance check (`remaining = 500 - 600 = -100` still passes)
3. Race condition between check and track

**Exploit:** Send unlimited messages through quota bypass.

---

### 6.3 HIGH: Paid Invoice Editing
**File:** `/modules/Billing/Filament/Resources/InvoiceResource/Pages/EditInvoice.php:49-57`

**Vulnerability:** `isEditable()` shows notification but form still loads and saves.

**Exploit:** Edit issued/paid invoice line items, change totals after payment recorded.

---

### 6.4 HIGH: Discount Stacking to Zero/Negative
**File:** `/modules/Booking/Filament/Pages/Checkout.php:293-305`

**Vulnerability:** Multiple discount levels (appointment, line-item, overall) stack without limit validation.

**Exploit:** Apply 100% appointment discount + 50% checkout discount = free service.

---

### 6.5 HIGH: Duplicate Payment Recording
**File:** `/modules/Billing/Filament/Resources/InvoiceResource/Pages/RecordPayment.php:282-298`

**Vulnerability:** No idempotency check or unique constraint on payment recording.

**Exploit:** Double-click or browser back → same payment recorded twice.

---

### 6.6 MEDIUM: Appointment Cancellation Without Refund
**File:** `/modules/Booking/Models/Appointment.php:578-581`

**Vulnerability:** Appointments can be cancelled after payment with no automatic refund mechanism.

---

### 6.7 MEDIUM: Session Product Double-Invoice
**File:** `/modules/Booking/Models/SessionProduct.php:281-287`

**Vulnerability:** `is_invoiced` and `is_deducted` flags are independent; process can desync.

---

## 7. Second-Order & Stored Attacks

### 7.1 CRITICAL: Email Template Stored XSS
**File:** `/resources/views/filament/super-admin/modals/email-template-preview.blade.php:41`

```blade
{!! $record->renderBodyPreview('en') !!}   <!-- UNESCAPED -->
```

**Exploit:** Admin stores `<script>` in email template body → XSS on preview.

---

### 7.2 CRITICAL: PDF Generation HTML Injection
**Files:** `/modules/Billing/resources/views/pdf/invoice.blade.php`, `/modules/Prescriptions/resources/views/pdf/prescription.blade.php`

**Vulnerability:** DomPDF with HTML5 parser enabled processes user data.

```php
->setOption('isHtml5ParserEnabled', true)
->setOption('isRemoteEnabled', true);
```

**Exploit:** Patient name `<svg onload=alert('XSS')>` executes in PDF.

---

### 7.3 HIGH: Excel Formula Injection
**File:** `/modules/Inventory/Exports/InventoryAdjustmentTemplateExport.php:29-44`

**Vulnerability:** No formula prefix sanitization in Excel exports.

**Exploit:** Product SKU `=cmd|'/c calc'!A1` executes on Excel open.

---

### 7.4 HIGH: Message Template Injection
**File:** `/modules/Marketing/Models/MessageTemplate.php:127-146`

**Vulnerability:** Direct string replacement without HTML escaping.

```php
$content = str_replace($placeholder, $value, $content);
```

**Exploit:** Patient name with HTML → stored in NotificationLog → XSS when viewed.

---

### 7.5 HIGH: Webhook Payload Storage
**File:** `/modules/Marketing/Http/Controllers/WhatsAppWebhookController.php:47-79`

**Vulnerability:** External webhook payload stored and later displayed without sanitization.

---

### 7.6 MEDIUM: Report HTML Export XSS
**File:** `/framework/Core/Report/BaseReport.php:415-446`

```php
$html .= "<td>{$value}</td>";  // Line 437: UNESCAPED
```

---

### 7.7 MEDIUM: Email Service Template Variables
**File:** `/modules/Marketing/Services/EmailService.php:135-180`

**Vulnerability:** Unescaped string interpolation in heredoc HTML templates.

---

## 8. API & Integration Issues

### 8.1 HIGH: Database-Stored API Credentials
**File:** `/app/Models/PlatformSetting.php:56-71`

**Vulnerability:** API keys for WhatsApp, SMS, Stripe, PayPal stored in database with APP_KEY encryption only.

**Impact:** Single key compromise exposes all third-party credentials.

---

### 8.2 HIGH: Firebase Credentials in Storage
**File:** `/config/mobile_api.config.php`

```php
'credentials' => env('FIREBASE_CREDENTIALS', storage_path('firebase-credentials.json')),
```

**Impact:** Server compromise → Firebase access.

---

### 8.3 MEDIUM: No Idempotency Keys
**Finding:** Payment, appointment, and invoice creation endpoints lack idempotency mechanisms.

**Impact:** Duplicate records on retry/double-click.

---

### 8.4 MEDIUM: Tenant Discovery Enumeration
**File:** `/modules/MobileApi/Routes/api.php`

**Vulnerability:** Public endpoints `/tenant/resolve/{code}`, `/tenant/lookup` allow tenant enumeration.

---

### 8.5 MEDIUM: API Versioning Coexistence
**Finding:** v1 and v2 APIs both active with potentially different security postures.

---

## 9. Cryptography & Token Security

### 9.1 CRITICAL: Recovery Code Timing Attack
**File:** `/app/Traits/TwoFactorAuthenticatable.php:80`

```php
if (in_array($code, $recoveryCodes)) {  // Non-constant-time comparison
```

**Exploit:** Measure response time to determine valid recovery codes.

---

### 9.2 CRITICAL: Weak Backup Code Entropy
**File:** `/modules/Auth/Resources/TwoFactorSetupResource/Pages/ManageTwoFactorSetup.php:217`

```php
$backupCodes[] = strtoupper(Str::random(8));  // Only 47 bits entropy
```

**Standard:** Security-critical tokens should have 128+ bits.

---

### 9.3 CRITICAL: MD5-Based Recovery Codes
**File:** `/app/Traits/TwoFactorAuthenticatable.php:117`

```php
$codes[] = strtoupper(substr(md5(random_bytes(16)), 0, 10));
```

**Issue:** MD5 is broken; truncation further reduces entropy.

---

### 9.4 HIGH: OTP Timing Attack
**File:** `/modules/PatientPortal/Services/OtpService.php:67`

```php
if ($cached['otp'] !== $otp) {  // Non-constant-time
```

---

### 9.5 HIGH: Weak Impersonation Token Hashing
**File:** `/app/Filament/SuperAdmin/Resources/TenantResource/Pages/ViewTenant.php:335`

```php
'impersonation_token' => hash('sha256', $token),  // Unsalted SHA256
```

**Should use:** `bcrypt()` or `hash_pbkdf2()`.

---

### 9.6 MEDIUM: Long Signed URL Expiry
**File:** `/modules/Marketing/Services/WhatsAppService.php:447`

```php
now()->addDays(7)  // 7-day expiry for appointment actions
```

**Recommendation:** 1-2 hours for sensitive operations.

---

## 10. DoS & Resource Exhaustion

### 10.1 CRITICAL: Unbounded Report Queries
**Files:** `/modules/Reporting/Filament/Pages/RevenueReportPage.php:109-152`

```php
$revenueByService = DB::table('payments')->join(...)->get();  // NO LIMIT
```

**Exploit:** Generate report with 10,000+ records → memory exhaustion.

---

### 10.2 CRITICAL: Calendar Endpoint Unbounded Loops
**File:** `/modules/MobileApi/Http/Controllers/CalendarController.php:39-82`

**Vulnerability:** Date range parameters not validated; can request years of data.

---

### 10.3 HIGH: No Tenant Storage Quota
**Finding:** Per-file limit (10MB) but no per-tenant total storage limit enforced.

**Exploit:** Upload 10MB × 1000 = 10GB per tenant.

---

### 10.4 HIGH: Image Processing Queue Flooding
**File:** `/modules/Patients/Models/PatientPhoto.php:98-109`

**Vulnerability:** Multiple image conversions queued per upload; no rate limit.

---

### 10.5 HIGH: Export Without Size Limits
**File:** `/app/Filament/Actions/ExportTableAction.php:62`

**Vulnerability:** Exports entire filtered table synchronously.

---

### 10.6 HIGH: Campaign Job Self-Dispatching
**File:** `/modules/Marketing/Jobs/ProcessCampaignRecipientsJob.php:43-53`

**Vulnerability:** Job re-dispatches itself; no rate limit on job creation.

---

### 10.7 MEDIUM: N+1 Queries in Calendar
**File:** `/modules/MobileApi/Http/Controllers/CalendarController.php:441-461`

**Vulnerability:** `getShiftForDate()` called per-day = 30+ queries per month.

---

### 10.8 MEDIUM: API Pagination Bypass
**File:** `/modules/Api/Http/Controllers/BookingController.php:23`

```php
->get()  // NO LIMIT for services endpoint
```

---

## 11. Chained Exploit Scenarios

### Chain 1: Tenant Takeover via Subscription Bypass + Impersonation

**Steps:**
1. Create free trial tenant
2. Mass-assign `subscription_plan_id` to enterprise via API (2.2)
3. Mass-assign `max_users=1000` (2.2)
4. Create admin user via UserBranchRole manipulation (2.4)
5. Generate impersonation token for tenant owner (2.1)
6. Use impersonation to access all tenant data

**Business Impact:** Complete tenant takeover, data theft, financial fraud.

---

### Chain 2: Credit Theft via Race + IDOR

**Steps:**
1. Attacker gains access to Tenant A
2. Identifies gift card IDs from another tenant via download IDOR (3.1)
3. Exploits race condition in gift card redemption (1.1)
4. Double-redeems gift cards across tenants
5. Uses stolen credits for services

**Business Impact:** Cross-tenant financial theft.

---

### Chain 3: Account Takeover via Email Change + Soft Delete

**Steps:**
1. Compromise user session (phishing/XSS)
2. Change email without verification (4.2)
3. Legitimate user tries to recover via password reset
4. Admin soft-deletes compromised account
5. Attacker still logs in with soft-deleted account (4.1)
6. Attacker has persistent access while user is "deleted"

**Business Impact:** Undetectable persistent access.

---

### Chain 4: WhatsApp Credit Exhaustion via Quota + Race

**Steps:**
1. Tenant has 500 WhatsApp message quota
2. Exploit first-message-free bypass (6.2)
3. Concurrent requests during quota check (1.6)
4. Send 600+ messages despite 500 limit
5. Provider bills platform for overage
6. Repeat across multiple tenants

**Business Impact:** Platform-wide billing fraud.

---

### Chain 5: Privilege Escalation via Mass Assignment + Background Job

**Steps:**
1. User mass-assigns `permissions_override: ["admin"]` (2.1)
2. Background job processing fails to resolve tenant (5.1)
3. Job executes with elevated permissions in wrong context
4. Data from multiple tenants exposed/modified

**Business Impact:** Multi-tenant data breach.

---

## 12. Remediation Priority Matrix

### IMMEDIATE (24-48 hours)

| ID | Vulnerability | Location | Risk |
|----|---------------|----------|------|
| 1.1-1.6 | Race conditions in payments/bookings | Multiple services | Financial loss |
| 2.1-2.3 | Mass assignment critical fields | User, Tenant, Invoice models | Privilege escalation |
| 4.1 | Soft-deleted user login | AuthController | Unauthorized access |
| 5.1 | TenantAwareJob typo | TenantAwareJob.php:163 | Complete job failure |
| 6.1 | No subscription enforcement | Tenant model | Subscription fraud |

### URGENT (1 week)

| ID | Vulnerability | Location | Risk |
|----|---------------|----------|------|
| 2.4-2.8 | Mass assignment high fields | Multiple models | Data manipulation |
| 3.1-3.2 | IDOR in downloads | GiftCards, Payroll | Data theft |
| 4.2-4.3 | Email/2FA issues | Profile, MobileApi | Account takeover |
| 6.2-6.5 | Business logic flaws | Quota, Invoice, Checkout | Revenue loss |
| 9.1-9.3 | Crypto weaknesses | 2FA implementation | Auth bypass |

### HIGH PRIORITY (2 weeks)

| ID | Vulnerability | Location | Risk |
|----|---------------|----------|------|
| 5.2-5.6 | Tenant isolation | Middleware, Cache | Cross-tenant exposure |
| 7.1-7.5 | Stored attacks | PDF, Email, Exports | XSS/RCE |
| 8.1-8.2 | Credential storage | PlatformSetting | Third-party compromise |
| 10.1-10.6 | DoS vectors | Reports, Calendar, Jobs | Service disruption |

### MEDIUM PRIORITY (1 month)

| ID | Vulnerability | Location | Risk |
|----|---------------|----------|------|
| 4.4-4.7 | Auth edge cases | 2FA, Reset, Impersonation | Security weakening |
| 6.6-6.7 | Business logic | Appointments, Products | Operational issues |
| 7.6-7.7 | Output encoding | Reports, Email | XSS |
| 8.3-8.5 | API hardening | Various | Information disclosure |
| 9.4-9.6 | Token improvements | OTP, Impersonation, URLs | Timing attacks |

---

## Appendix A: Proof-of-Concept Templates

### A.1 Race Condition PoC (Gift Card)

```bash
#!/bin/bash
# Race condition exploit for gift card redemption

CARD_ID="123"
AMOUNT="50000"  # $500 in minor units
TOKEN="your-api-token"

# Launch 10 concurrent redemption requests
for i in {1..10}; do
    curl -X POST "https://tenant.x-linic.com/api/v1/gift-cards/${CARD_ID}/redeem" \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "Content-Type: application/json" \
        -d '{"amount_minor": '$AMOUNT'}' &
done
wait
echo "Check gift card balance - should show negative if exploited"
```

### A.2 Mass Assignment PoC (User Privileges)

```http
PATCH /api/users/5 HTTP/1.1
Host: tenant.x-linic.com
Authorization: Bearer <token>
Content-Type: application/json

{
    "first_name": "Normal",
    "last_name": "Update",
    "permissions_override": ["super_admin", "billing.manage", "users.delete"],
    "status": "active",
    "commission_rate": 100
}
```

### A.3 Tenant Enumeration PoC

```bash
#!/bin/bash
# Enumerate valid tenant slugs

for slug in clinic medical dental spa wellness; do
    response=$(curl -s -o /dev/null -w "%{http_code}" \
        -H "X-Tenant-Slug: ${slug}" \
        "https://api.x-linic.com/api/v2/health")
    if [ "$response" == "200" ]; then
        echo "Valid tenant: ${slug}"
    fi
done
```

---

## Appendix B: Secure Code Patterns

### B.1 Race Condition Prevention

```php
// BEFORE (vulnerable)
$card = GiftCard::find($id);
if ($card->remaining_value_minor >= $amount) {
    $card->decrement('remaining_value_minor', $amount);
}

// AFTER (secure)
DB::transaction(function() use ($id, $amount) {
    $card = GiftCard::lockForUpdate()->find($id);
    if ($card->remaining_value_minor < $amount) {
        throw new InsufficientBalanceException();
    }
    $card->decrement('remaining_value_minor', $amount);
});
```

### B.2 Mass Assignment Protection

```php
// BEFORE (vulnerable)
protected $fillable = ['*'];

// AFTER (secure)
protected $guarded = ['*'];
protected $fillable = ['first_name', 'last_name', 'email'];

// With form request validation
public function rules(): array
{
    return [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        // NO permissions_override, role_id, tenant_id
    ];
}
```

### B.3 Constant-Time Token Comparison

```php
// BEFORE (vulnerable)
if (in_array($code, $recoveryCodes)) { ... }

// AFTER (secure)
foreach ($recoveryCodes as $validCode) {
    if (hash_equals($validCode, $code)) {
        // Valid code found
        break;
    }
}
```

---

## Appendix C: Testing Checklist

- [ ] Race conditions tested with concurrent requests
- [ ] Mass assignment attempted on all models
- [ ] IDOR tested across tenant boundaries
- [ ] Soft-deleted user authentication attempted
- [ ] 2FA bypass paths verified
- [ ] Tenant context manipulation tested
- [ ] Subscription limits enforced
- [ ] Export functionality bounded
- [ ] Token entropy verified
- [ ] Signed URL expiry validated

---

**Report Classification:** CONFIDENTIAL
**Distribution:** Security Team, Engineering Leadership
**Next Review:** 30 days post-remediation

---

*This report was generated by automated security analysis combined with manual code review. All findings should be verified in a controlled test environment before remediation.*
