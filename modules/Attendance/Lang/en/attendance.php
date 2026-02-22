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
        'mobile' => 'Mobile App',
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
];
