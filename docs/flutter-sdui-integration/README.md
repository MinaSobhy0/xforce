# XLinic Staff Mobile App - SDUI Integration Guide

This guide provides Flutter code for integrating with XLinic's Server-Driven UI (SDUI) API.

## Overview

The mobile app uses SDUI to dynamically render screens based on server configuration. This allows SuperAdmin to customize each tenant's mobile app appearance without requiring app updates.

## Architecture

```
┌─────────────────────────────────────────┐
│           Mobile App (Flutter)           │
├─────────────────────────────────────────┤
│  1. Tenant Discovery (app code entry)   │
│  2. Auth (login, token storage)         │
│  3. SDUI Renderer (parse & render JSON) │
│  4. Navigation (from /api/v2/config)    │
│  5. Screens (from /api/v2/screens/*)    │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│         XLinic API (/api/v2)            │
└─────────────────────────────────────────┘
```

## Project Structure

```
lib/
├── core/
│   ├── api/
│   │   └── api_client.dart         # HTTP client with auth & tenant headers
│   └── sdui/
│       ├── sdui_renderer.dart      # Component registry & rendering
│       └── sdui_screen.dart        # Screen widget with loading states
└── main.dart                       # App entry, auth flow, main screen
```

## Setup

### 1. Add Dependencies

Add to `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter
  dio: ^5.4.0
  flutter_riverpod: ^2.4.9
  riverpod_annotation: ^2.3.3
  flutter_secure_storage: ^9.0.0
  go_router: ^13.0.0
  json_annotation: ^4.8.1

dev_dependencies:
  build_runner: ^2.4.8
  riverpod_generator: ^2.3.9
  json_serializable: ^6.7.1
```

Then run:
```bash
flutter pub get
```

### 2. Copy Source Files

Copy the following files from this directory into your Flutter project:

- `lib/core/api/api_client.dart`
- `lib/core/sdui/sdui_renderer.dart`
- `lib/core/sdui/sdui_screen.dart`
- `lib/main.dart`

### 3. Configure Base URL

In `api_client.dart`, the base URL is constructed from the tenant slug:
```dart
String get baseUrl => 'https://$_tenantSlug.x-linic.com/api/v2';
```

For development, you may want to use:
```dart
String get baseUrl => 'http://10.0.2.2:8000/api/v2'; // Android emulator
// or
String get baseUrl => 'http://localhost:8000/api/v2'; // iOS simulator
```

## API Endpoints

### Public Endpoints (no auth required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v2/tenant/resolve/{code}` | Resolve app code to tenant |
| GET | `/api/v2/config` | Get app config (branding, navigation, features) |
| GET | `/api/v2/branding` | Get branding only |
| GET | `/api/v2/navigation` | Get navigation configuration |
| POST | `/api/v2/auth/staff/login` | Staff login |

### Authenticated Endpoints (Bearer token required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v2/screens/{screen}` | Get SDUI screen definition |
| GET | `/api/v2/screens/{screen}/data` | Get screen data only (for refresh) |
| GET | `/api/v2/attendance/status` | Get current attendance status |
| POST | `/api/v2/attendance/check-in` | Check in |
| POST | `/api/v2/attendance/check-out` | Check out |

### Required Headers

```
X-Tenant-Slug: {tenant_slug}        # Required for all tenant endpoints
Authorization: Bearer {token}        # Required for authenticated endpoints
Accept: application/json
Content-Type: application/json
```

## SDUI Component Types

The renderer must handle these component types:

| Type | Description | Props |
|------|-------------|-------|
| `attendance_status` | Check-in/out card | `actions: [check_in, check_out, break]` |
| `stats_grid` | 2x2 statistics grid | `items: [{key, label, requires}]` |
| `upcoming_appointments` | Appointment list | `limit: number` |
| `quick_actions` | Action buttons | `items: [{key, icon, requires}]` |
| `attendance_card` | Full attendance details | `show_check_in_time, show_breaks` |
| `balance_cards` | Leave balance cards | `types: [vacation, sick, personal]` |
| `profile_header` | User profile header | `show_avatar, show_employee_number` |

## App Flow

1. **Splash Screen** - Check for existing session
2. **Tenant Code Entry** - User enters clinic app code
3. **Login Screen** - Email/password authentication
4. **Main Screen** - SDUI-driven tabs from navigation config

## Response Formats

### Config Response (`GET /api/v2/config`)

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
        {"id": "more", "label": "More", "icon": "ellipsis-horizontal"}
      ],
      "more_menu": [
        {"id": "payslip", "label": "Payslip", "icon": "document-text"},
        {"id": "time_off", "label": "Time Off", "icon": "sun"},
        {"id": "profile", "label": "Profile", "icon": "user-circle"}
      ]
    },
    "features": {
      "attendance": true,
      "payroll": true,
      "booking": true,
      "attendance_photo_required": false,
      "break_tracking": true,
      "geofence_check_in": true,
      "qr_check_in": true
    }
  }
}
```

### Screen Response (`GET /api/v2/screens/dashboard`)

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
        "props": {
          "actions": ["check_in", "check_out", "break"]
        }
      },
      {
        "type": "stats_grid",
        "sort": 2,
        "props": {
          "items": [
            {"key": "today_appointments", "label": "Today's Appointments"},
            {"key": "completed", "label": "Completed"},
            {"key": "pending_commission", "label": "Pending Commission"},
            {"key": "hours_today", "label": "Hours Today"}
          ]
        }
      },
      {
        "type": "upcoming_appointments",
        "sort": 3,
        "props": {
          "limit": 3
        }
      }
    ]
  }
}
```

## Testing

### Test Credentials

Contact your admin for test tenant app codes and staff credentials.

### API Testing with cURL

```bash
# Resolve tenant
curl https://sys.x-linic.com/api/v2/tenant/resolve/ABC123

# Get config
curl -H "X-Tenant-Slug: demo-clinic" \
  https://demo-clinic.x-linic.com/api/v2/config

# Login
curl -X POST \
  -H "X-Tenant-Slug: demo-clinic" \
  -H "Content-Type: application/json" \
  -d '{"email":"staff@example.com","password":"password"}' \
  https://demo-clinic.x-linic.com/api/v2/auth/staff/login

# Get screen
curl -H "X-Tenant-Slug: demo-clinic" \
  -H "Authorization: Bearer {token}" \
  https://demo-clinic.x-linic.com/api/v2/screens/dashboard
```

## Support

For API issues, contact the backend team.
