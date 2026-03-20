<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile API Language Lines - Arabic
    |--------------------------------------------------------------------------
    */

    'general' => [
        'success' => 'تم بنجاح',
        'error' => 'خطأ',
        'unauthorized' => 'غير مصرح',
        'forbidden' => 'الوصول مرفوض',
        'not_found' => 'المورد غير موجود',
        'validation_error' => 'خطأ في التحقق',
        'server_error' => 'خطأ في الخادم',
    ],

    'auth' => [
        'login_success' => 'تم تسجيل الدخول بنجاح',
        'login_failed' => 'بيانات اعتماد غير صالحة',
        'logout_success' => 'تم تسجيل الخروج بنجاح',
        'token_refresh' => 'تم تحديث الرمز',
        'invalid_token' => 'رمز غير صالح أو منتهي الصلاحية',
        '2fa_required' => 'مطلوب المصادقة الثنائية',
        '2fa_invalid' => 'رمز التحقق غير صالح',
        '2fa_success' => 'تم التحقق من المصادقة الثنائية',
        'account_disabled' => 'تم تعطيل حسابك',
        'not_staff' => 'فقط الموظفين يمكنهم الوصول إلى هذا التطبيق',
    ],

    'tenant' => [
        'invalid_code' => 'رمز العيادة غير صالح',
        'code_expired' => 'انتهت صلاحية هذا الرمز',
        'code_usage_exceeded' => 'وصل هذا الرمز إلى حد الاستخدام',
        'tenant_not_found' => 'العيادة غير موجودة',
        'tenant_inactive' => 'هذه العيادة غير نشطة حالياً',
        'resolved' => 'تم العثور على العيادة',
        'validated' => 'تم التحقق من العيادة',
    ],

    'attendance' => [
        'checked_in' => 'تم تسجيل الحضور بنجاح',
        'checked_out' => 'تم تسجيل الانصراف بنجاح',
        'already_checked_in' => 'أنت مسجل حضور بالفعل',
        'already_completed_today' => 'تم اكتمال الحضور لهذا اليوم',
        'not_checked_in' => 'لم يتم تسجيل حضورك',
        'break_started' => 'بدأت الاستراحة',
        'break_ended' => 'انتهت الاستراحة',
        'not_on_break' => 'أنت لست في استراحة',
        'already_on_break' => 'أنت في استراحة بالفعل',
        'invalid_location' => 'أنت لست ضمن المنطقة المسموح بها',
        'location_required' => 'الموقع مطلوب لتسجيل الحضور بالموقع الجغرافي',
        'invalid_qr' => 'رمز QR غير صالح أو منتهي الصلاحية',
        'dispute_submitted' => 'تم تقديم الاعتراض بنجاح',
        'cannot_dispute' => 'لا يمكن الاعتراض على هذه المخالفة',
    ],

    'time_off' => [
        'request_submitted' => 'تم تقديم طلب الإجازة',
        'request_cancelled' => 'تم إلغاء طلب الإجازة',
        'cannot_cancel' => 'لا يمكن إلغاء هذا الطلب',
        'insufficient_balance' => 'رصيد الإجازة غير كافي',
        'dates_overlap' => 'هذه التواريخ تتداخل مع طلب موجود',
    ],

    'payroll' => [
        'no_payslip' => 'لا يوجد كشف راتب لهذه الفترة',
        'payslip_downloaded' => 'تم تحميل كشف الراتب',
    ],

    'schedule' => [
        'no_schedule' => 'لا يوجد جدول مخصص',
        'no_shift' => 'لا يوجد دوام لهذا التاريخ',
    ],

    'appointments' => [
        'session_started' => 'بدأت الجلسة',
        'session_completed' => 'اكتملت الجلسة',
        'notes_saved' => 'تم حفظ الملاحظات',
        'cannot_start' => 'لا يمكن بدء هذه الجلسة',
        'cannot_complete' => 'لا يمكن إكمال هذه الجلسة',
    ],

    'patients' => [
        'no_results' => 'لم يتم العثور على مرضى',
    ],

    'screens' => [
        'dashboard' => 'لوحة التحكم',
        'attendance' => 'الحضور',
        'time_off' => 'الإجازات',
        'payslip' => 'كشف الراتب',
        'schedule' => 'الجدول',
        'appointments' => 'المواعيد',
        'patients' => 'المرضى',
        'profile' => 'الملف الشخصي',
        'commission' => 'العمولات',
    ],

    'dashboard' => [
        'today_appointments' => 'مواعيد اليوم',
        'completed' => 'مكتمل',
        'pending_commission' => 'عمولة معلقة',
        'hours_today' => 'ساعات اليوم',
    ],

    'filament' => [
        'qr_page_title' => 'رمز QR لتطبيق الهاتف',
        'qr_page_description' => 'امسح رمز QR هذا باستخدام تطبيق XLinic للهاتف للاتصال بعيادتك',
        'download_qr' => 'تحميل رمز QR',
        'copy_link' => 'نسخ الرابط',
        'link_copied' => 'تم نسخ الرابط',
        'clinic_code' => 'رمز العيادة',
    ],
];
