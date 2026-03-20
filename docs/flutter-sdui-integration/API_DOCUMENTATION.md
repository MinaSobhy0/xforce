# XLinic Mobile API Documentation

**Base URL:** `https://{tenant_slug}.x-linic.com/api/v2`
**System URL:** `https://sys.x-linic.com/api/v2` (for tenant discovery only)

---

## Table of Contents

1. [Authentication](#authentication)
2. [Headers](#headers)
3. [Response Format](#response-format)
4. [Tenant Discovery](#tenant-discovery)
5. [App Configuration](#app-configuration)
6. [SDUI Screens](#sdui-screens)
7. [Staff Profile](#staff-profile)
8. [Attendance](#attendance)
9. [Appointments](#appointments)
10. [Time Off / Leave](#time-off--leave)
11. [Payroll](#payroll)
12. [Commission](#commission)
13. [Schedule](#schedule)
14. [Patients](#patients)
15. [Error Codes](#error-codes)

---

## Authentication

### Login Flow

1. User enters **App Code** (e.g., `ABC123`)
2. App calls `GET /tenant/resolve/{code}` to get tenant info
3. App stores `tenant_slug` and uses it in `X-Tenant-Slug` header
4. User enters email/password
5. App calls `POST /auth/staff/login`
6. App stores Bearer token for subsequent requests

---

## Headers

### Required Headers

| Header | Value | Required For |
|--------|-------|--------------|
| `Accept` | `application/json` | All requests |
| `Content-Type` | `application/json` | POST/PUT requests |
| `X-Tenant-Slug` | `{tenant_slug}` | All tenant endpoints |
| `Authorization` | `Bearer {token}` | Authenticated endpoints |

### Example

```http
GET /api/v2/staff/dashboard HTTP/1.1
Host: demo-clinic.x-linic.com
Accept: application/json
X-Tenant-Slug: demo-clinic
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

---

## Response Format

### Success Response

```json
{
  "success": true,
  "message": "Success",
  "data": { ... }
}
```

### Paginated Response

```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  },
  "links": {
    "first": "https://...?page=1",
    "last": "https://...?page=5",
    "prev": null,
    "next": "https://...?page=2"
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

---

## Tenant Discovery

### Resolve App Code

Converts a short app code to tenant information.

```http
GET /api/v2/tenant/resolve/{code}
Host: sys.x-linic.com
```

**Parameters:**
| Name | Type | Description |
|------|------|-------------|
| `code` | string | 6-character app code (e.g., `ABC123`) |

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 26,
    "name": "Beauty Clinic",
    "slug": "beauty-clinic",
    "logo_url": "https://beauty-clinic.x-linic.com/storage/logos/logo.png",
    "primary_color": "#3B82F6",
    "timezone": "Africa/Cairo",
    "currency": "EGP",
    "locale": "en"
  }
}
```

### Lookup by Domain

```http
GET /api/v2/tenant/lookup?domain={domain}
Host: sys.x-linic.com
```

---

## App Configuration

### Get Full Configuration

Returns branding, navigation, features, and settings.

```http
GET /api/v2/config
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "name": "Beauty Clinic",
      "slug": "beauty-clinic",
      "timezone": "Africa/Cairo",
      "currency": "EGP",
      "locale": "en"
    },
    "branding": {
      "app_name": "Beauty Staff",
      "primary_color": "#3B82F6",
      "secondary_color": "#1E40AF",
      "accent_color": "#F59E0B",
      "logo_url": "https://...",
      "dark_mode_enabled": true
    },
    "navigation": {
      "tabs": [
        {"id": "dashboard", "label": "Dashboard", "icon": "home"},
        {"id": "appointments", "label": "Appointments", "icon": "calendar"},
        {"id": "attendance", "label": "Attendance", "icon": "clock"},
        {"id": "schedule", "label": "Schedule", "icon": "calendar-days"},
        {"id": "more", "label": "More", "icon": "ellipsis-horizontal"}
      ],
      "more_menu": [
        {"id": "payslip", "label": "Payslip", "icon": "document-text"},
        {"id": "time_off", "label": "Time Off", "icon": "sun"},
        {"id": "commission", "label": "Commission", "icon": "currency-dollar"},
        {"id": "patients", "label": "Patients", "icon": "users"},
        {"id": "profile", "label": "Profile", "icon": "user-circle"}
      ]
    },
    "features": {
      "attendance": true,
      "payroll": true,
      "booking": true,
      "staff": true,
      "attendance_photo_required": false,
      "break_tracking": true,
      "geofence_check_in": true,
      "qr_check_in": true
    },
    "settings": {
      "check_in_methods": ["manual", "qr", "gps"],
      "geofence_enabled": true,
      "geofence_radius": 100,
      "break_tracking": true,
      "photo_check_in": false
    },
    "sdui": {
      "version": "1.0.0"
    },
    "api": {
      "version": "v2"
    }
  }
}
```

### Get Branding Only

```http
GET /api/v2/branding
```

**Response:**
```json
{
  "success": true,
  "data": {
    "app_name": "Beauty Staff",
    "primary_color": "#3B82F6",
    "secondary_color": "#1E40AF",
    "accent_color": "#F59E0B",
    "logo_url": "https://...",
    "dark_mode_enabled": true
  }
}
```

### Get Navigation Only

```http
GET /api/v2/navigation
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tabs": [
      {"id": "dashboard", "label": "Dashboard", "icon": "home"},
      {"id": "appointments", "label": "Appointments", "icon": "calendar"},
      {"id": "attendance", "label": "Attendance", "icon": "clock"},
      {"id": "more", "label": "More", "icon": "ellipsis-horizontal"}
    ],
    "more_menu": [
      {"id": "payslip", "label": "Payslip", "icon": "document-text"},
      {"id": "time_off", "label": "Time Off", "icon": "sun"},
      {"id": "profile", "label": "Profile", "icon": "user-circle"}
    ]
  }
}
```

---

## Authentication Endpoints

### Staff Login

```http
POST /api/v2/auth/staff/login
```

**Request Body:**
```json
{
  "email": "staff@clinic.com",
  "password": "password123"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "staff@clinic.com",
      "avatar_url": "https://...",
      "role": "staff"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_at": "2024-02-15T10:30:00Z"
  }
}
```

**Error Response (Invalid Credentials):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

**Error Response (2FA Required):**
```json
{
  "success": false,
  "message": "Two-factor authentication required",
  "data": {
    "requires_2fa": true,
    "temp_token": "temp_xyz..."
  }
}
```

### Verify 2FA

```http
POST /api/v2/auth/staff/2fa
```

**Request Body:**
```json
{
  "code": "123456",
  "temp_token": "temp_xyz..."
}
```

### Logout

```http
POST /api/v2/auth/logout
Authorization: Bearer {token}
```

### Refresh Token

```http
POST /api/v2/auth/refresh
Authorization: Bearer {token}
```

### Get Current User

```http
GET /api/v2/auth/me
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "staff@clinic.com",
    "phone": "+201234567890",
    "avatar_url": "https://...",
    "employee_number": "EMP001",
    "role": "staff",
    "branch": {
      "id": 1,
      "name": "Main Branch"
    },
    "permissions": ["view_appointments", "manage_attendance"]
  }
}
```

---

## SDUI Screens

### Get Screen Definition

Returns the layout and components for a screen.

```http
GET /api/v2/screens/{screen_id}
Authorization: Bearer {token}
```

**Available Screens:**
| Screen ID | Description |
|-----------|-------------|
| `dashboard` | Main dashboard |
| `appointments` | Appointments list |
| `attendance` | Attendance management |
| `schedule` | Work schedule |
| `payslip` | Payslip/Salary |
| `time_off` | Time off requests |
| `commission` | Commission earnings |
| `patients` | Patient search |
| `profile` | User profile |

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "dashboard",
    "title": "Dashboard",
    "version": "1.0.0",
    "timestamp": "2024-01-15T10:30:00Z",
    "components": [
      {
        "type": "attendance_status",
        "sort": 1,
        "enabled": true,
        "props": {
          "actions": ["check_in", "check_out", "break"]
        }
      },
      {
        "type": "stats_grid",
        "sort": 2,
        "enabled": true,
        "props": {
          "items": [
            {"key": "today_appointments", "label": "Today"},
            {"key": "completed", "label": "Completed"},
            {"key": "pending_commission", "label": "Commission"},
            {"key": "hours_today", "label": "Hours"}
          ]
        }
      },
      {
        "type": "upcoming_appointments",
        "sort": 3,
        "enabled": true,
        "props": {
          "limit": 3
        }
      },
      {
        "type": "quick_actions",
        "sort": 4,
        "enabled": true,
        "props": {
          "items": [
            {"key": "time_off", "icon": "sun"},
            {"key": "schedule", "icon": "calendar"},
            {"key": "patients", "icon": "users"}
          ]
        }
      }
    ]
  }
}
```

### Get Screen Data Only

For refreshing data without re-fetching layout.

```http
GET /api/v2/screens/{screen_id}/data
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "is_checked_in": true,
    "check_in_time": "09:15 AM",
    "is_on_break": false,
    "today_appointments": 8,
    "completed": 3,
    "pending_commission": "1,250 EGP",
    "hours_today": "4h 30m",
    "upcoming_appointments": [
      {
        "id": 123,
        "patient_name": "Sarah Ahmed",
        "service_name": "Laser Treatment",
        "time": "2:00 PM",
        "status": "confirmed"
      }
    ]
  }
}
```

### Component Types Reference

| Type | Description | Props |
|------|-------------|-------|
| `attendance_status` | Check-in/out card | `actions: string[]` |
| `stats_grid` | Statistics grid | `items: {key, label}[]` |
| `upcoming_appointments` | Appointment list | `limit: number` |
| `quick_actions` | Action buttons | `items: {key, icon}[]` |
| `attendance_card` | Detailed attendance | `show_breaks: boolean` |
| `tab_bar` | Tab navigation | `tabs: {id, label}[]` |
| `attendance_history` | History list | `days: number` |
| `balance_cards` | Leave balances | `types: string[]` |
| `request_button` | New request button | `label: string` |
| `requests_list` | Request list | `status: string` |
| `period_selector` | Month/year picker | `default: string` |
| `salary_summary` | Salary card | - |
| `earnings_breakdown` | Earnings details | - |
| `deductions_breakdown` | Deductions details | - |
| `download_button` | Download PDF | `format: string` |
| `week_calendar` | Week view | - |
| `shift_card` | Shift details | - |
| `working_hours` | Hours summary | - |
| `date_picker` | Date selector | - |
| `appointments_list` | Full appointments | `filter: string` |
| `search_bar` | Search input | `placeholder: string` |
| `patients_list` | Patient results | - |
| `profile_header` | Profile header | `show_avatar: boolean` |
| `profile_details` | Profile info | - |
| `action_list` | Menu actions | `items: {key, icon}[]` |
| `commission_summary` | Commission card | - |
| `commission_plan` | Plan details | - |
| `commission_history` | History list | - |

---

## Staff Profile

### Get Dashboard Data

```http
GET /api/v2/staff/dashboard
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "avatar_url": "https://..."
    },
    "attendance": {
      "is_checked_in": true,
      "check_in_time": "09:15",
      "is_on_break": false,
      "hours_today": "4h 30m"
    },
    "stats": {
      "today_appointments": 8,
      "completed_appointments": 3,
      "pending_commission": 1250.00,
      "monthly_earnings": 15000.00
    },
    "upcoming_appointments": [
      {
        "id": 123,
        "patient_name": "Sarah Ahmed",
        "service_name": "Laser Treatment",
        "time": "14:00",
        "duration": 60,
        "status": "confirmed"
      }
    ]
  }
}
```

### Get Profile

```http
GET /api/v2/staff/profile
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@clinic.com",
    "phone": "+201234567890",
    "avatar_url": "https://...",
    "employee_number": "EMP001",
    "job_title": "Senior Therapist",
    "department": "Laser",
    "branch": {
      "id": 1,
      "name": "Main Branch",
      "address": "123 Main St"
    },
    "hire_date": "2023-01-15",
    "contract_type": "full_time"
  }
}
```

### Update Profile

```http
PUT /api/v2/staff/profile
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "phone": "+201234567890",
  "avatar": "base64_encoded_image..."
}
```

---

## Attendance

### Get Attendance Status

```http
GET /api/v2/attendance/status
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "is_checked_in": true,
    "check_in_time": "2024-01-15T09:15:00Z",
    "check_in_method": "qr",
    "is_on_break": false,
    "current_break_start": null,
    "total_break_minutes": 30,
    "expected_check_out": "2024-01-15T18:00:00Z",
    "shift": {
      "id": 1,
      "name": "Morning Shift",
      "start_time": "09:00",
      "end_time": "18:00"
    }
  }
}
```

### Get Attendance Settings

```http
GET /api/v2/attendance/settings
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "check_in_methods": ["manual", "qr", "gps"],
    "photo_required": false,
    "geofence_enabled": true,
    "geofence_radius": 100,
    "break_tracking": true,
    "max_break_minutes": 60,
    "locations": [
      {
        "id": 1,
        "name": "Main Branch",
        "latitude": 30.0444,
        "longitude": 31.2357,
        "radius": 100
      }
    ]
  }
}
```

### Check In

```http
POST /api/v2/attendance/check-in
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "method": "qr",
  "qr_code": "QR_CODE_DATA",
  "latitude": 30.0444,
  "longitude": 31.2357,
  "photo": "base64_encoded_image..."
}
```

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `method` | string | Yes | `manual`, `qr`, or `gps` |
| `qr_code` | string | If method=qr | QR code data |
| `latitude` | number | If method=gps | Current latitude |
| `longitude` | number | If method=gps | Current longitude |
| `photo` | string | If required | Base64 image |

**Response:**
```json
{
  "success": true,
  "message": "Checked in successfully",
  "data": {
    "attendance_id": 456,
    "check_in_time": "2024-01-15T09:15:00Z",
    "method": "qr"
  }
}
```

### Check Out

```http
POST /api/v2/attendance/check-out
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "latitude": 30.0444,
  "longitude": 31.2357,
  "photo": "base64_encoded_image..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Checked out successfully",
  "data": {
    "attendance_id": 456,
    "check_out_time": "2024-01-15T18:05:00Z",
    "total_hours": "8h 50m",
    "overtime": "50m"
  }
}
```

### Start Break

```http
POST /api/v2/attendance/break/start
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Break started",
  "data": {
    "break_id": 789,
    "start_time": "2024-01-15T13:00:00Z"
  }
}
```

### End Break

```http
POST /api/v2/attendance/break/end
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Break ended",
  "data": {
    "break_id": 789,
    "duration_minutes": 45
  }
}
```

### Get Attendance History

```http
GET /api/v2/attendance/history?page=1&per_page=15
Authorization: Bearer {token}
```

**Query Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 15 | Items per page |
| `start_date` | date | - | Filter start |
| `end_date` | date | - | Filter end |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 456,
      "date": "2024-01-15",
      "check_in_time": "09:15",
      "check_out_time": "18:05",
      "total_hours": "8h 50m",
      "break_minutes": 45,
      "status": "present",
      "is_late": false,
      "is_early_leave": false
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

### Get Attendance Summary

```http
GET /api/v2/attendance/summary?month=2024-01
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "period": "January 2024",
    "total_days": 22,
    "present_days": 20,
    "absent_days": 1,
    "late_days": 2,
    "total_hours": "176h 30m",
    "overtime_hours": "8h 15m",
    "early_leaves": 0
  }
}
```

### Validate QR Code

```http
POST /api/v2/attendance/validate/qr
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "qr_code": "QR_CODE_DATA"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "branch": {
      "id": 1,
      "name": "Main Branch"
    }
  }
}
```

### Validate Geofence

```http
POST /api/v2/attendance/validate/geofence
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "latitude": 30.0444,
  "longitude": 31.2357
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "within_radius": true,
    "distance_meters": 45,
    "branch": {
      "id": 1,
      "name": "Main Branch"
    }
  }
}
```

### Get Geofence Locations

```http
GET /api/v2/attendance/geofence/locations
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Main Branch",
      "latitude": 30.0444,
      "longitude": 31.2357,
      "radius": 100
    },
    {
      "id": 2,
      "name": "Downtown Branch",
      "latitude": 30.0500,
      "longitude": 31.2400,
      "radius": 150
    }
  ]
}
```

### Get Attendance Violations

```http
GET /api/v2/attendance/violations
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "date": "2024-01-10",
      "type": "late_arrival",
      "description": "Arrived 30 minutes late",
      "status": "pending",
      "can_dispute": true
    }
  ]
}
```

### Dispute Violation

```http
POST /api/v2/attendance/violations/{id}/dispute
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "reason": "Traffic accident on the highway"
}
```

---

## Appointments

### Get Today's Appointments

```http
GET /api/v2/appointments/today
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "patient": {
        "id": 456,
        "name": "Sarah Ahmed",
        "phone": "+201234567890",
        "avatar_url": null
      },
      "service": {
        "id": 1,
        "name": "Laser Hair Removal",
        "duration": 60,
        "color": "#3B82F6"
      },
      "date": "2024-01-15",
      "start_time": "14:00",
      "end_time": "15:00",
      "status": "confirmed",
      "notes": "First session",
      "room": "Room 3"
    }
  ]
}
```

### Get Appointments

```http
GET /api/v2/appointments?date=2024-01-15
Authorization: Bearer {token}
```

**Query Parameters:**
| Name | Type | Description |
|------|------|-------------|
| `date` | date | Specific date |
| `start_date` | date | Range start |
| `end_date` | date | Range end |
| `status` | string | Filter by status |
| `page` | int | Page number |

### Get Single Appointment

```http
GET /api/v2/appointments/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "patient": {
      "id": 456,
      "name": "Sarah Ahmed",
      "phone": "+201234567890",
      "email": "sarah@email.com",
      "avatar_url": null,
      "medical_notes": "No allergies"
    },
    "service": {
      "id": 1,
      "name": "Laser Hair Removal",
      "duration": 60,
      "price": 500.00
    },
    "date": "2024-01-15",
    "start_time": "14:00",
    "end_time": "15:00",
    "status": "confirmed",
    "notes": "First session",
    "internal_notes": "Uses numbing cream",
    "room": "Room 3",
    "created_at": "2024-01-10T10:00:00Z",
    "history": [
      {
        "date": "2023-12-15",
        "service": "Consultation",
        "notes": "Initial assessment"
      }
    ]
  }
}
```

### Start Appointment

```http
POST /api/v2/appointments/{id}/start
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Appointment started",
  "data": {
    "id": 123,
    "status": "in_progress",
    "started_at": "2024-01-15T14:05:00Z"
  }
}
```

### Complete Appointment

```http
POST /api/v2/appointments/{id}/complete
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "notes": "Treatment completed successfully",
  "products_used": [
    {"product_id": 1, "quantity": 2}
  ]
}
```

### Add Appointment Notes

```http
POST /api/v2/appointments/{id}/notes
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "notes": "Patient requested follow-up in 4 weeks"
}
```

---

## Time Off / Leave

### Get Time Off Types

```http
GET /api/v2/time-off/types
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Annual Leave",
      "code": "annual",
      "color": "#10B981",
      "requires_approval": true,
      "max_days": 21
    },
    {
      "id": 2,
      "name": "Sick Leave",
      "code": "sick",
      "color": "#EF4444",
      "requires_approval": true,
      "requires_document": true,
      "max_days": 15
    }
  ]
}
```

### Get Leave Balance

```http
GET /api/v2/time-off/balance
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "year": 2024,
    "balances": [
      {
        "type": "annual",
        "name": "Annual Leave",
        "total": 21,
        "used": 5,
        "pending": 2,
        "remaining": 14
      },
      {
        "type": "sick",
        "name": "Sick Leave",
        "total": 15,
        "used": 2,
        "pending": 0,
        "remaining": 13
      }
    ]
  }
}
```

### Get Leave Requests

```http
GET /api/v2/time-off/requests?status=pending
Authorization: Bearer {token}
```

**Query Parameters:**
| Name | Type | Description |
|------|------|-------------|
| `status` | string | `pending`, `approved`, `rejected`, `cancelled` |
| `year` | int | Filter by year |
| `page` | int | Page number |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": {
        "id": 1,
        "name": "Annual Leave",
        "code": "annual"
      },
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "days": 5,
      "reason": "Family vacation",
      "status": "pending",
      "submitted_at": "2024-01-15T10:00:00Z",
      "reviewed_by": null,
      "reviewed_at": null
    }
  ]
}
```

### Submit Leave Request

```http
POST /api/v2/time-off/requests
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "type_id": 1,
  "start_date": "2024-02-01",
  "end_date": "2024-02-05",
  "reason": "Family vacation",
  "document": "base64_encoded_file..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Leave request submitted",
  "data": {
    "id": 1,
    "status": "pending",
    "days": 5
  }
}
```

### Cancel Leave Request

```http
POST /api/v2/time-off/requests/{id}/cancel
Authorization: Bearer {token}
```

### Get Team Calendar

```http
GET /api/v2/time-off/calendar?month=2024-02
Authorization: Bearer {token}
```

---

## Payroll

### Get Current Payslip

```http
GET /api/v2/payroll/current
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "period": "January 2024",
    "status": "pending",
    "payment_date": null,
    "summary": {
      "basic_salary": 10000.00,
      "allowances": 2000.00,
      "overtime": 500.00,
      "commission": 1500.00,
      "gross_salary": 14000.00,
      "deductions": 1400.00,
      "net_salary": 12600.00
    },
    "currency": "EGP"
  }
}
```

### Get Payslip History

```http
GET /api/v2/payroll/history?year=2024
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "period": "January 2024",
      "net_salary": 12600.00,
      "status": "paid",
      "payment_date": "2024-01-28"
    }
  ]
}
```

### Get Payslip Details

```http
GET /api/v2/payroll/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "period": "January 2024",
    "period_start": "2024-01-01",
    "period_end": "2024-01-31",
    "earnings": [
      {"type": "basic_salary", "label": "Basic Salary", "amount": 10000.00},
      {"type": "transport", "label": "Transport Allowance", "amount": 500.00},
      {"type": "housing", "label": "Housing Allowance", "amount": 1500.00},
      {"type": "overtime", "label": "Overtime (8h)", "amount": 500.00},
      {"type": "commission", "label": "Commission", "amount": 1500.00}
    ],
    "deductions": [
      {"type": "tax", "label": "Income Tax", "amount": 1000.00},
      {"type": "insurance", "label": "Social Insurance", "amount": 400.00}
    ],
    "gross_salary": 14000.00,
    "total_deductions": 1400.00,
    "net_salary": 12600.00,
    "status": "paid",
    "payment_date": "2024-01-28",
    "payment_method": "bank_transfer"
  }
}
```

### Download Payslip PDF

```http
GET /api/v2/payroll/{id}/payslip
Authorization: Bearer {token}
```

Returns PDF file.

### Get Salary Structure

```http
GET /api/v2/payroll/salary-structure
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "basic_salary": 10000.00,
    "allowances": [
      {"type": "transport", "name": "Transport", "amount": 500.00},
      {"type": "housing", "name": "Housing", "amount": 1500.00}
    ],
    "deductions": [
      {"type": "tax", "name": "Income Tax", "percentage": 10},
      {"type": "insurance", "name": "Social Insurance", "percentage": 4}
    ]
  }
}
```

---

## Commission

### Get Commission Summary

```http
GET /api/v2/staff/commission
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_month": {
      "total": 2500.00,
      "pending": 500.00,
      "paid": 2000.00
    },
    "this_year": {
      "total": 28000.00
    },
    "plan": {
      "name": "Senior Therapist Plan",
      "rates": [
        {"service_category": "Laser", "percentage": 15},
        {"service_category": "Facial", "percentage": 10}
      ]
    }
  }
}
```

### Get Commission History

```http
GET /api/v2/staff/commission/history?month=2024-01
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "date": "2024-01-15",
      "appointment_id": 123,
      "patient_name": "Sarah Ahmed",
      "service_name": "Laser Treatment",
      "service_amount": 500.00,
      "commission_rate": 15,
      "commission_amount": 75.00,
      "status": "pending"
    }
  ]
}
```

### Get Pending Commission

```http
GET /api/v2/staff/commission/pending
Authorization: Bearer {token}
```

---

## Schedule

### Get Current Week Schedule

```http
GET /api/v2/schedule/current
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "week_start": "2024-01-15",
    "week_end": "2024-01-21",
    "days": [
      {
        "date": "2024-01-15",
        "day": "Monday",
        "is_working": true,
        "shift": {
          "id": 1,
          "name": "Morning",
          "start_time": "09:00",
          "end_time": "18:00"
        }
      },
      {
        "date": "2024-01-16",
        "day": "Tuesday",
        "is_working": true,
        "shift": {
          "id": 1,
          "name": "Morning",
          "start_time": "09:00",
          "end_time": "18:00"
        }
      },
      {
        "date": "2024-01-19",
        "day": "Friday",
        "is_working": false,
        "reason": "Day off"
      }
    ],
    "total_hours": 40
  }
}
```

### Get Shifts

```http
GET /api/v2/schedule/shifts?start_date=2024-01-01&end_date=2024-01-31
Authorization: Bearer {token}
```

### Get Shift for Date

```http
GET /api/v2/schedule/shifts/{date}
Authorization: Bearer {token}
```

### Get Working Hours Summary

```http
GET /api/v2/schedule/working-hours?month=2024-01
Authorization: Bearer {token}
```

---

## Patients

### Search Patients

```http
GET /api/v2/patients/search?q=sarah
Authorization: Bearer {token}
```

**Query Parameters:**
| Name | Type | Description |
|------|------|-------------|
| `q` | string | Search query (name, phone, email) |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 456,
      "name": "Sarah Ahmed",
      "phone": "+201234567890",
      "email": "sarah@email.com",
      "avatar_url": null,
      "last_visit": "2024-01-10"
    }
  ]
}
```

### Get Patient Details

```http
GET /api/v2/patients/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 456,
    "name": "Sarah Ahmed",
    "phone": "+201234567890",
    "email": "sarah@email.com",
    "gender": "female",
    "date_of_birth": "1990-05-15",
    "age": 33,
    "blood_type": "A+",
    "allergies": ["Penicillin"],
    "medical_notes": "No chronic conditions",
    "total_visits": 12,
    "total_spent": 6500.00,
    "last_visit": "2024-01-10",
    "created_at": "2023-06-15"
  }
}
```

### Get Patient Appointments

```http
GET /api/v2/patients/{id}/appointments
Authorization: Bearer {token}
```

### Get Patient Visits History

```http
GET /api/v2/patients/{id}/visits
Authorization: Bearer {token}
```

---

## Error Codes

| HTTP Code | Error | Description |
|-----------|-------|-------------|
| 400 | Bad Request | Invalid request parameters |
| 401 | Unauthorized | Missing or invalid token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 422 | Validation Error | Request validation failed |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Internal server error |

### Common Error Responses

**401 Unauthorized:**
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

**403 Forbidden:**
```json
{
  "success": false,
  "message": "You do not have permission to perform this action"
}
```

**422 Validation Error:**
```json
{
  "success": false,
  "message": "The given data was invalid",
  "errors": {
    "email": ["The email field is required"],
    "password": ["The password must be at least 8 characters"]
  }
}
```

---

## Rate Limits

| Endpoint Type | Limit |
|---------------|-------|
| Authentication | 5 requests/minute |
| General API | 60 requests/minute |
| File uploads | 10 requests/minute |

---

## Versioning

The API uses URL versioning (`/api/v2`). Breaking changes will result in a new version.

---

## Support

For API issues or questions, contact the backend team.
