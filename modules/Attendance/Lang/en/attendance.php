<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Information
    |--------------------------------------------------------------------------
    */
    'module_name' => 'Attendance',
    'module_description' => 'Attendance tracking, working schedules, and violation management',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resources
    |--------------------------------------------------------------------------
    */
    'attendances' => 'Attendance',
    'attendance' => 'Attendance',
    'working_schedules' => 'Working Schedules',
    'working_schedule' => 'Working Schedule',
    'attendance_rules' => 'Attendance Rules',
    'attendance_rule' => 'Attendance Rule',
    'violations' => 'Violations',
    'violation' => 'Violation',
    'attendance_logs' => 'Attendance Logs',
    'attendance_log' => 'Attendance Log',
    'breaks' => 'Breaks',

    /*
    |--------------------------------------------------------------------------
    | Common Fields
    |--------------------------------------------------------------------------
    */
    'staff' => 'Staff',
    'staff_profile' => 'Staff Profile',
    'branch' => 'Branch',
    'date' => 'Date',
    'attendance_date' => 'Attendance Date',
    'check_in' => 'Check In',
    'check_out' => 'Check Out',
    'check_in_time' => 'Check In Time',
    'check_out_time' => 'Check Out Time',
    'working_hours' => 'Working Hours',
    'late_hours' => 'Late Hours',
    'late_minutes' => 'Late Minutes',
    'early_hours' => 'Early Checkout Hours',
    'overtime_hours' => 'Overtime Hours',
    'status' => 'Status',
    'type' => 'Type',
    'notes' => 'Notes',
    'reason' => 'Reason',
    'late_reason' => 'Late Reason',
    'early_checkout_reason' => 'Early Checkout Reason',
    'approved_by' => 'Approved By',
    'approved_at' => 'Approved At',
    'created_by' => 'Created By',
    'is_active' => 'Active',

    /*
    |--------------------------------------------------------------------------
    | Attendance Types
    |--------------------------------------------------------------------------
    */
    'types' => [
        'manual' => 'Manual',
        'geofence' => 'Geofence',
        'qr_static' => 'Static QR',
        'qr_dynamic' => 'Dynamic QR',
        'biometric' => 'Biometric',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance Statuses
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'present' => 'Present',
        'absent' => 'Absent',
        'half_day' => 'Half Day',
        'leave' => 'On Leave',
    ],

    /*
    |--------------------------------------------------------------------------
    | Working Schedule
    |--------------------------------------------------------------------------
    */
    'schedule_name' => 'Schedule Name',
    'schedule_code' => 'Schedule Code',
    'schedule_type' => 'Schedule Type',
    'start_time' => 'Start Time',
    'end_time' => 'End Time',
    'hours_per_day' => 'Hours Per Day',
    'hours_per_week' => 'Hours Per Week',
    'days_per_week' => 'Days Per Week',
    'grace_period_late' => 'Grace Period (Late)',
    'grace_period_early' => 'Grace Period (Early)',
    'working_days' => 'Working Days',
    'is_flexible' => 'Flexible Schedule',
    'is_default' => 'Default Schedule',
    'core_hours' => 'Core Hours',
    'core_hours_start' => 'Core Hours Start',
    'core_hours_end' => 'Core Hours End',
    'allow_overtime' => 'Allow Overtime',
    'max_overtime_per_day' => 'Max Overtime Per Day',
    'has_break' => 'Has Break',
    'break_duration' => 'Break Duration (minutes)',
    'break_start' => 'Break Start',
    'break_end' => 'Break End',

    'schedule_types' => [
        'fixed' => 'Fixed Hours',
        'flexible' => 'Flexible Hours',
        'shift' => 'Shift Work',
        'compressed' => 'Compressed Week',
        'remote' => 'Remote',
    ],

    'days' => [
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance Rules
    |--------------------------------------------------------------------------
    */
    'rule_name' => 'Rule Name',
    'rule_code' => 'Rule Code',
    'rule_category' => 'Rule Category',
    'auto_apply' => 'Auto Apply',
    'send_notification' => 'Send Notification',
    'notify_manager' => 'Notify Manager',
    'notify_hr' => 'Notify HR',

    'rule_categories' => [
        'late_checkin' => 'Late Check-In',
        'early_checkout' => 'Early Check-Out',
        'missed_checkin' => 'Missed Check-In',
        'missed_checkout' => 'Missed Check-Out',
        'overstay' => 'Overstay',
        'unauthorized_absence' => 'Unauthorized Absence',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rule Actions
    |--------------------------------------------------------------------------
    */
    'action_type' => 'Action Type',
    'occurrence_number' => 'Occurrence Number',
    'severity' => 'Severity',
    'threshold_type' => 'Threshold Type',
    'threshold_value' => 'Threshold Value',
    'threshold_period' => 'Threshold Period',
    'penalty_type' => 'Penalty Type',
    'penalty_amount' => 'Penalty Amount',
    'penalty_percentage' => 'Penalty Percentage',
    'penalty_formula' => 'Penalty Formula',
    'requires_approval' => 'Requires Approval',
    'message_template' => 'Message Template',

    'action_types' => [
        'deduction' => 'Salary Deduction',
        'warning' => 'Warning Notice',
        'approval_required' => 'Require Approval',
        'notification' => 'Send Notification',
        'block_attendance' => 'Block Attendance',
    ],

    'penalty_types' => [
        'fixed' => 'Fixed Amount',
        'percentage' => 'Percentage of Daily Salary',
        'hourly_rate' => 'Hourly Rate Deduction',
        'formula' => 'Custom Formula',
    ],

    'threshold_types' => [
        'time_based' => 'Time Based (Minutes)',
        'occurrence_based' => 'Occurrence Based (Count)',
    ],

    'severities' => [
        'minor' => 'Minor',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
    ],

    'periods' => [
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
    ],

    /*
    |--------------------------------------------------------------------------
    | Violations
    |--------------------------------------------------------------------------
    */
    'violation_type' => 'Violation Type',
    'violation_date' => 'Violation Date',
    'scheduled_time' => 'Scheduled Time',
    'actual_time' => 'Actual Time',
    'grace_period_minutes' => 'Grace Period (minutes)',
    'violation_minutes' => 'Violation Minutes',
    'penalty_amount_minor' => 'Penalty Amount',
    'employee_notes' => 'Employee Notes',
    'manager_notes' => 'Manager Notes',
    'waived_by' => 'Waived By',
    'waived_reason' => 'Waived Reason',
    'waived_at' => 'Waived At',
    'dispute_reason' => 'Dispute Reason',
    'disputed_at' => 'Disputed At',
    'applied_at' => 'Applied At',

    'violation_statuses' => [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'waived' => 'Waived',
        'disputed' => 'Disputed',
        'applied' => 'Applied to Payslip',
        'cancelled' => 'Cancelled',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance Logs
    |--------------------------------------------------------------------------
    */
    'log_type' => 'Log Type',
    'latitude' => 'Latitude',
    'longitude' => 'Longitude',
    'altitude' => 'Altitude',
    'address' => 'Address',
    'source' => 'Source',
    'device_info' => 'Device Info',

    'log_types' => [
        'check_in' => 'Check In',
        'check_out' => 'Check Out',
        'break_start' => 'Break Start',
        'break_end' => 'Break End',
    ],

    'sources' => [
        'manual' => 'Manual Entry',
        'mobile' => 'Mobile App',
        'biometric' => 'Biometric Device',
        'web' => 'Web Portal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */
    'check_in_action' => 'Check In',
    'check_out_action' => 'Check Out',
    'start_break' => 'Start Break',
    'end_break' => 'End Break',
    'approve' => 'Approve',
    'waive' => 'Waive',
    'dispute' => 'Dispute',
    'cancel' => 'Cancel',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */
    'checked_in_successfully' => 'Checked in successfully',
    'checked_out_successfully' => 'Checked out successfully',
    'already_checked_in' => 'Already checked in today',
    'not_checked_in' => 'Not checked in yet',
    'violation_approved' => 'Violation approved successfully',
    'violation_waived' => 'Violation waived successfully',
    'violation_disputed' => 'Violation disputed successfully',

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    'attendance_report' => 'Attendance Report',
    'violation_report' => 'Violation Report',
    'summary_report' => 'Summary Report',
    'daily_report' => 'Daily Report',
    'monthly_report' => 'Monthly Report',
    'present_count' => 'Present',
    'absent_count' => 'Absent',
    'late_count' => 'Late',
    'on_leave_count' => 'On Leave',

    'reports' => [
        'navigation' => 'Attendance Reports',
        'title' => 'Attendance Reports',
        'report_type' => 'Report Type',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'all_branches' => 'All Branches',
        'all_staff' => 'All Staff',
        'generate' => 'Generate Report',
        'export' => 'Export CSV',
        'types' => [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'custom' => 'Custom Range',
        ],
        'stats' => [
            'total_records' => 'Total Records',
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'leave' => 'Leave',
            'half_day' => 'Half Day',
            'attendance_rate' => 'Attendance Rate',
            'violations' => 'Violations',
            'total_working_hours' => 'Total Working Hours',
            'avg_working_hours' => 'Avg Working Hours',
            'total_overtime' => 'Total Overtime',
            'total_late_minutes' => 'Total Late Minutes',
        ],
        'chart' => [
            'title' => 'Attendance Trend',
        ],
        'table' => [
            'title' => 'Attendance Records',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'present_today' => 'Present Today',
        'checked_in' => 'Checked In',
        'checked_out' => 'Checked Out',
        'late_absent' => 'Late & Absent',
        'late' => 'Late',
        'absent' => 'Absent',
        'on_leave' => 'On Leave',
        'pending_violations' => 'Pending Violations',
        'avg_hours' => 'Avg. Hours',
        'vs_last_week' => 'vs last week',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'title' => 'Attendance Settings',
        'scope' => 'Settings Scope',
        'scope_description' => 'Configure settings globally or per branch',
        'branch' => 'Branch',
        'global_settings' => 'Global Settings (All Branches)',
        'enabled' => 'Enable this check-in method',
        'save' => 'Save Settings',
        'saved' => 'Settings saved successfully',
        'save_error' => 'Failed to save settings',
        'qr_regenerated' => 'QR code regenerated successfully',
        'secret_regenerated' => 'Secret key regenerated successfully',

        'manual' => [
            'title' => 'Manual Check-in',
            'description' => 'Plain in-app check-in without QR or biometrics. Disable it to force staff onto verified methods.',
            'toggle_help' => 'Enabled by default. When disabled, manual check-in is refused for everyone in this scope (location rules still apply to other methods).',
        ],
        'geofence' => [
            'title' => 'Geofence Settings',
            'description' => 'Configure GPS-based location verification for attendance',
            'radius' => 'Default Radius',
            'radius_help' => 'Default geofence radius in meters',
            'min_accuracy' => 'Minimum GPS Accuracy',
            'require_accuracy' => 'Require High Accuracy',
            'allow_mock' => 'Allow Mock Locations',
            'allow_mock_help' => 'Warning: Enabling this allows fake GPS locations',
            'check_checkout' => 'Verify Location on Checkout',
            'locations' => 'Geofence Locations',
            'location_name' => 'Location Name',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'location_radius' => 'Custom Radius',
            'add_location' => 'Add Location',
        ],

        'qr_static' => [
            'title' => 'Static QR Code Settings',
            'description' => 'Configure permanent QR codes for attendance check-in',
            'camera_only' => 'Camera Only Mode',
            'camera_only_help' => 'Require camera scan, disable manual code entry',
            'show_in_app' => 'Show QR in Staff App',
            'show_in_app_help' => 'Allow staff to view QR code in their app',
            'require_location' => 'Require Location',
            'qr_code' => 'QR Code',
            'qr_generated_info' => 'QR code will be automatically generated when enabled',
            'regenerate' => 'Regenerate QR Code',
        ],

        'qr_dynamic' => [
            'title' => 'Dynamic QR Code Settings',
            'description' => 'Configure time-based rotating QR codes for enhanced security',
            'refresh_interval' => 'Refresh Interval',
            'refresh_interval_help' => 'How often the QR code changes',
            'validity' => 'Code Validity',
            'validity_help' => 'How long a code remains valid after generation',
            'algorithm' => 'Algorithm',
            'display_countdown' => 'Display Countdown Timer',
            'require_location' => 'Require Location',
            'regenerate_secret' => 'Regenerate Secret Key',
        ],

        'biometric' => [
            'title' => 'Biometric Settings',
            'description' => 'Configure biometric device integration for attendance',
            'device_type' => 'Biometric Type',
            'fingerprint' => 'Fingerprint',
            'face' => 'Face Recognition',
            'iris' => 'Iris Scan',
            'verification_level' => 'Verification Level',
            'verification_level_help' => 'Higher levels are more secure but may have more false rejections',
            'level_low' => 'Low (Fast, less secure)',
            'level_medium' => 'Medium (Balanced)',
            'level_high' => 'High (Secure, slower)',
            'allow_fallback' => 'Allow Fallback Method',
            'fallback_method' => 'Fallback Method',
            'api_endpoint' => 'Device API Endpoint',
            'api_key' => 'API Key',
            'device_ids' => 'Registered Device IDs',
            'device_ids_placeholder' => 'Enter device ID',
            'device_ids_help' => 'Add device serial numbers or IDs',
        ],
    ],
];
