import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// API Client for XLinic Staff Mobile App
///
/// Handles:
/// - Tenant context (X-Tenant-Slug header)
/// - Authentication (Bearer token)
/// - Session persistence
/// - All API endpoints
class ApiClient {
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  String? _tenantSlug;
  String? _authToken;

  ApiClient() {
    _dio = Dio(BaseOptions(
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        // Add tenant header
        if (_tenantSlug != null) {
          options.headers['X-Tenant-Slug'] = _tenantSlug;
        }
        // Add auth token
        if (_authToken != null) {
          options.headers['Authorization'] = 'Bearer $_authToken';
        }
        return handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // Token expired - trigger logout
          await clearAuth();
        }
        return handler.next(error);
      },
    ));
  }

  // ============================================================
  // CONFIGURATION
  // ============================================================

  /// Base URL for tenant API (set after tenant discovery)
  String get baseUrl => 'https://$_tenantSlug.x-linic.com/api/v2';

  /// System URL for tenant discovery
  static const String systemUrl = 'https://sys.x-linic.com/api/v2';

  // ============================================================
  // TENANT DISCOVERY
  // ============================================================

  /// Resolve app code to tenant information
  ///
  /// Returns tenant data including slug, name, logo, etc.
  Future<Map<String, dynamic>> resolveTenant(String appCode) async {
    final response = await _dio.get('$systemUrl/tenant/resolve/$appCode');
    return response.data['data'];
  }

  /// Set the current tenant context
  Future<void> setTenant(String slug) async {
    _tenantSlug = slug;
    await _storage.write(key: 'tenant_slug', value: slug);
  }

  /// Get current tenant slug
  String? get tenantSlug => _tenantSlug;

  // ============================================================
  // AUTHENTICATION
  // ============================================================

  /// Login with email and password
  ///
  /// Returns user data and token on success
  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await _dio.post(
      '$baseUrl/auth/staff/login',
      data: {'email': email, 'password': password},
    );

    if (response.data['success'] == true) {
      _authToken = response.data['data']['token'];
      await _storage.write(key: 'auth_token', value: _authToken);
    }

    return response.data;
  }

  /// Verify 2FA code (if required)
  Future<Map<String, dynamic>> verify2fa(String code) async {
    final response = await _dio.post(
      '$baseUrl/auth/staff/2fa',
      data: {'code': code},
    );

    if (response.data['success'] == true) {
      _authToken = response.data['data']['token'];
      await _storage.write(key: 'auth_token', value: _authToken);
    }

    return response.data;
  }

  /// Logout current user
  Future<void> logout() async {
    try {
      await _dio.post('$baseUrl/auth/logout');
    } finally {
      await clearAuth();
    }
  }

  /// Clear authentication data
  Future<void> clearAuth() async {
    _authToken = null;
    await _storage.delete(key: 'auth_token');
  }

  /// Restore session from secure storage
  ///
  /// Returns true if valid session exists
  Future<bool> restoreSession() async {
    _tenantSlug = await _storage.read(key: 'tenant_slug');
    _authToken = await _storage.read(key: 'auth_token');
    return _tenantSlug != null && _authToken != null;
  }

  /// Check if user is authenticated
  bool get isAuthenticated => _authToken != null;

  /// Get current user info
  Future<Map<String, dynamic>> getCurrentUser() async {
    final response = await _dio.get('$baseUrl/auth/me');
    return response.data['data'];
  }

  // ============================================================
  // APP CONFIGURATION
  // ============================================================

  /// Get full app configuration
  ///
  /// Returns branding, navigation, features, settings
  Future<Map<String, dynamic>> getConfig() async {
    final response = await _dio.get('$baseUrl/config');
    return response.data['data'];
  }

  /// Get branding configuration only
  Future<Map<String, dynamic>> getBranding() async {
    final response = await _dio.get('$baseUrl/branding');
    return response.data['data'];
  }

  /// Get navigation configuration only
  Future<Map<String, dynamic>> getNavigation() async {
    final response = await _dio.get('$baseUrl/navigation');
    return response.data['data'];
  }

  // ============================================================
  // SDUI SCREENS
  // ============================================================

  /// Get screen definition (layout + components)
  Future<Map<String, dynamic>> getScreen(String screenId) async {
    final response = await _dio.get('$baseUrl/screens/$screenId');
    return response.data['data'];
  }

  /// Get screen data only (for refresh without re-fetching layout)
  Future<Map<String, dynamic>> getScreenData(String screenId) async {
    final response = await _dio.get('$baseUrl/screens/$screenId/data');
    return response.data['data'];
  }

  // ============================================================
  // STAFF PROFILE
  // ============================================================

  /// Get staff dashboard data
  Future<Map<String, dynamic>> getDashboard() async {
    final response = await _dio.get('$baseUrl/staff/dashboard');
    return response.data['data'];
  }

  /// Get staff profile
  Future<Map<String, dynamic>> getProfile() async {
    final response = await _dio.get('$baseUrl/staff/profile');
    return response.data['data'];
  }

  /// Update staff profile
  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> data) async {
    final response = await _dio.put('$baseUrl/staff/profile', data: data);
    return response.data['data'];
  }

  // ============================================================
  // ATTENDANCE
  // ============================================================

  /// Get current attendance status
  Future<Map<String, dynamic>> getAttendanceStatus() async {
    final response = await _dio.get('$baseUrl/attendance/status');
    return response.data['data'];
  }

  /// Get attendance settings (geofence locations, etc.)
  Future<Map<String, dynamic>> getAttendanceSettings() async {
    final response = await _dio.get('$baseUrl/attendance/settings');
    return response.data['data'];
  }

  /// Check in
  Future<Map<String, dynamic>> checkIn({
    String? method,
    String? qrCode,
    double? latitude,
    double? longitude,
    String? photoBase64,
  }) async {
    final response = await _dio.post('$baseUrl/attendance/check-in', data: {
      if (method != null) 'method': method,
      if (qrCode != null) 'qr_code': qrCode,
      if (latitude != null) 'latitude': latitude,
      if (longitude != null) 'longitude': longitude,
      if (photoBase64 != null) 'photo': photoBase64,
    });
    return response.data['data'];
  }

  /// Check out
  Future<Map<String, dynamic>> checkOut({
    double? latitude,
    double? longitude,
    String? photoBase64,
  }) async {
    final response = await _dio.post('$baseUrl/attendance/check-out', data: {
      if (latitude != null) 'latitude': latitude,
      if (longitude != null) 'longitude': longitude,
      if (photoBase64 != null) 'photo': photoBase64,
    });
    return response.data['data'];
  }

  /// Start break
  Future<Map<String, dynamic>> startBreak() async {
    final response = await _dio.post('$baseUrl/attendance/break/start');
    return response.data['data'];
  }

  /// End break
  Future<Map<String, dynamic>> endBreak() async {
    final response = await _dio.post('$baseUrl/attendance/break/end');
    return response.data['data'];
  }

  /// Get attendance history
  Future<Map<String, dynamic>> getAttendanceHistory({
    int page = 1,
    int perPage = 15,
  }) async {
    final response = await _dio.get(
      '$baseUrl/attendance/history',
      queryParameters: {'page': page, 'per_page': perPage},
    );
    return response.data;
  }

  /// Validate QR code
  Future<Map<String, dynamic>> validateQr(String qrCode) async {
    final response = await _dio.post(
      '$baseUrl/attendance/validate/qr',
      data: {'qr_code': qrCode},
    );
    return response.data['data'];
  }

  /// Validate geofence location
  Future<Map<String, dynamic>> validateGeofence(double lat, double lng) async {
    final response = await _dio.post(
      '$baseUrl/attendance/validate/geofence',
      data: {'latitude': lat, 'longitude': lng},
    );
    return response.data['data'];
  }

  /// Get geofence locations
  Future<List<dynamic>> getGeofenceLocations() async {
    final response = await _dio.get('$baseUrl/attendance/geofence/locations');
    return response.data['data'];
  }

  // ============================================================
  // APPOINTMENTS
  // ============================================================

  /// Get today's appointments
  Future<List<dynamic>> getTodayAppointments() async {
    final response = await _dio.get('$baseUrl/appointments/today');
    return response.data['data'];
  }

  /// Get appointments for a date range
  Future<Map<String, dynamic>> getAppointments({
    String? date,
    String? startDate,
    String? endDate,
    int page = 1,
  }) async {
    final response = await _dio.get(
      '$baseUrl/appointments',
      queryParameters: {
        if (date != null) 'date': date,
        if (startDate != null) 'start_date': startDate,
        if (endDate != null) 'end_date': endDate,
        'page': page,
      },
    );
    return response.data;
  }

  /// Get single appointment details
  Future<Map<String, dynamic>> getAppointment(int id) async {
    final response = await _dio.get('$baseUrl/appointments/$id');
    return response.data['data'];
  }

  /// Start appointment
  Future<Map<String, dynamic>> startAppointment(int id) async {
    final response = await _dio.post('$baseUrl/appointments/$id/start');
    return response.data['data'];
  }

  /// Complete appointment
  Future<Map<String, dynamic>> completeAppointment(int id) async {
    final response = await _dio.post('$baseUrl/appointments/$id/complete');
    return response.data['data'];
  }

  // ============================================================
  // TIME OFF
  // ============================================================

  /// Get time off balance
  Future<Map<String, dynamic>> getTimeOffBalance() async {
    final response = await _dio.get('$baseUrl/time-off/balance');
    return response.data['data'];
  }

  /// Get time off requests
  Future<Map<String, dynamic>> getTimeOffRequests({int page = 1}) async {
    final response = await _dio.get(
      '$baseUrl/time-off/requests',
      queryParameters: {'page': page},
    );
    return response.data;
  }

  /// Submit time off request
  Future<Map<String, dynamic>> submitTimeOffRequest(Map<String, dynamic> data) async {
    final response = await _dio.post('$baseUrl/time-off/requests', data: data);
    return response.data['data'];
  }

  /// Cancel time off request
  Future<void> cancelTimeOffRequest(int id) async {
    await _dio.post('$baseUrl/time-off/requests/$id/cancel');
  }

  // ============================================================
  // PAYROLL
  // ============================================================

  /// Get current payslip
  Future<Map<String, dynamic>> getCurrentPayslip() async {
    final response = await _dio.get('$baseUrl/payroll/current');
    return response.data['data'];
  }

  /// Get payslip history
  Future<Map<String, dynamic>> getPayslipHistory({int page = 1}) async {
    final response = await _dio.get(
      '$baseUrl/payroll/history',
      queryParameters: {'page': page},
    );
    return response.data;
  }

  /// Get specific payslip
  Future<Map<String, dynamic>> getPayslip(int id) async {
    final response = await _dio.get('$baseUrl/payroll/$id');
    return response.data['data'];
  }

  // ============================================================
  // COMMISSION
  // ============================================================

  /// Get commission summary
  Future<Map<String, dynamic>> getCommission() async {
    final response = await _dio.get('$baseUrl/staff/commission');
    return response.data['data'];
  }

  /// Get commission history
  Future<Map<String, dynamic>> getCommissionHistory({int page = 1}) async {
    final response = await _dio.get(
      '$baseUrl/staff/commission/history',
      queryParameters: {'page': page},
    );
    return response.data;
  }

  // ============================================================
  // PATIENTS
  // ============================================================

  /// Search patients
  Future<Map<String, dynamic>> searchPatients(String query, {int page = 1}) async {
    final response = await _dio.get(
      '$baseUrl/patients/search',
      queryParameters: {'q': query, 'page': page},
    );
    return response.data;
  }

  /// Get patient details
  Future<Map<String, dynamic>> getPatient(int id) async {
    final response = await _dio.get('$baseUrl/patients/$id');
    return response.data['data'];
  }

  // ============================================================
  // SCHEDULE
  // ============================================================

  /// Get current week schedule
  Future<Map<String, dynamic>> getCurrentSchedule() async {
    final response = await _dio.get('$baseUrl/schedule/current');
    return response.data['data'];
  }

  /// Get shifts for date range
  Future<List<dynamic>> getShifts({String? startDate, String? endDate}) async {
    final response = await _dio.get(
      '$baseUrl/schedule/shifts',
      queryParameters: {
        if (startDate != null) 'start_date': startDate,
        if (endDate != null) 'end_date': endDate,
      },
    );
    return response.data['data'];
  }

  // ============================================================
  // GENERIC REQUESTS
  // ============================================================

  /// Generic GET request
  Future<Map<String, dynamic>> get(String path) async {
    final response = await _dio.get('$baseUrl$path');
    return response.data['data'];
  }

  /// Generic POST request
  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) async {
    final response = await _dio.post('$baseUrl$path', data: data);
    return response.data['data'];
  }

  /// Generic PUT request
  Future<Map<String, dynamic>> put(String path, {Map<String, dynamic>? data}) async {
    final response = await _dio.put('$baseUrl$path', data: data);
    return response.data['data'];
  }

  /// Generic DELETE request
  Future<void> delete(String path) async {
    await _dio.delete('$baseUrl$path');
  }
}
