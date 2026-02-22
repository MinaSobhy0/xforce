<?php

return [
    // Module
    'api' => 'API',

    // Auth
    'login_success' => 'تم تسجيل الدخول بنجاح',
    'logout_success' => 'تم تسجيل الخروج بنجاح',
    'logout_all_success' => 'تم تسجيل الخروج من جميع الأجهزة',
    'invalid_credentials' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
    'patient_not_found' => 'رقم الهاتف غير مسجل',
    'account_inactive' => 'الحساب غير نشط',
    'otp_sent' => 'تم إرسال رمز التحقق',
    'otp_send_failed' => 'فشل إرسال رمز التحقق',
    'otp_expired' => 'انتهت صلاحية رمز التحقق',
    'invalid_otp' => 'رمز التحقق غير صحيح',

    // Profile
    'profile_updated' => 'تم تحديث الملف الشخصي بنجاح',

    // Booking
    'booking_success' => 'تم حجز الموعد بنجاح',
    'booking_failed' => 'فشل حجز الموعد',
    'booking_too_soon' => 'لا يمكن حجز موعد بأقل من الوقت المطلوب',
    'slot_not_available' => 'هذا الوقت لم يعد متاحاً',
    'appointment_not_found' => 'الموعد غير موجود',
    'appointment_cancelled' => 'تم إلغاء الموعد بنجاح',
    'cannot_cancel' => 'لا يمكن إلغاء هذا الموعد',
    'cancel_too_late' => 'لا يمكن إلغاء الموعد خلال :hours ساعة من الموعد المحدد',
    'cancelled_via_api' => 'تم الإلغاء عبر API',

    // Gift Cards
    'gift_card_not_found' => 'بطاقة الهدية غير موجودة أو منتهية الصلاحية',

    // Attendance
    'staff_profile_not_found' => 'ملف الموظف غير موجود',
    'no_schedule_assigned' => 'لا يوجد جدول عمل محدد',
    'already_on_break' => 'أنت بالفعل في استراحة',
    'not_on_break' => 'لست في استراحة حالياً',
    'break_started' => 'تم بدء الاستراحة بنجاح',
    'break_ended' => 'تم إنهاء الاستراحة بنجاح',
    'violation_not_found' => 'المخالفة غير موجودة',
    'cannot_dispute_violation' => 'لا يمكن الاعتراض على هذه المخالفة',

    // Modules
    'module_not_active' => 'ميزة :module غير متاحة',

    // Errors
    'unauthorized' => 'غير مصرح',
    'forbidden' => 'الوصول مرفوض',
    'not_found' => 'المورد غير موجود',
    'validation_failed' => 'فشل التحقق',
    'server_error' => 'خطأ في الخادم الداخلي',
];
