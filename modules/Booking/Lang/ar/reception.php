<?php

return [
    // Navigation
    'navigation' => 'الاستقبال',
    'title' => 'لوحة الاستقبال',
    'heading' => 'لوحة الاستقبال',

    // Sections
    'sections' => [
        'appointments' => 'مواعيد اليوم',
        'patient_flow' => 'تدفق المرضى',
    ],

    // Statistics
    'stats' => [
        'total' => 'الإجمالي',
        'total_desc' => 'مواعيد اليوم',
        'waiting' => 'بالانتظار',
        'waiting_desc' => 'في منطقة الانتظار',
        'in_rooms' => 'في الغرف',
        'in_rooms_desc' => 'تم تعيين غرفة',
        'in_progress' => 'جاري',
        'in_progress_desc' => 'مع الطبيب',
        'completed' => 'مكتمل',
        'completed_desc' => 'انتهى اليوم',
        'no_shows' => 'لم يحضر',
        'no_shows_desc' => 'لم يصل',
    ],

    // Statuses
    'statuses' => [
        'scheduled' => 'مجدول',
        'confirmed' => 'مؤكد',
        'checked_in' => 'تم الحضور',
        'in_progress' => 'جاري',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغى',
        'no_show' => 'لم يحضر',
        'rescheduled' => 'أعيدت جدولته',
    ],

    // Columns
    'columns' => [
        'time' => 'الوقت',
        'patient' => 'المريض',
        'service' => 'الخدمة',
        'doctor' => 'الطبيب',
        'room' => 'الغرفة',
        'wait_time' => 'وقت الانتظار',
        'status' => 'الحالة',
    ],

    // Filters
    'filters' => [
        'status' => 'الحالة',
        'practitioner' => 'الممارس',
        'room' => 'الغرفة',
        'search' => 'ابحث عن مريض...',
        'date' => 'التاريخ',
        'previous_day' => 'اليوم السابق',
        'next_day' => 'اليوم التالي',
        'today' => 'اليوم',
        'viewing_today' => 'عرض اليوم',
        'viewing_date' => 'عرض تاريخ آخر',
    ],

    // Actions
    'actions' => [
        'check_in' => 'تسجيل الحضور',
        'checking_in' => 'جاري التسجيل',
        'assign_room' => 'تعيين غرفة',
        'assign_doctor' => 'تعيين طبيب',
        'start' => 'بدء الجلسة',
        'no_show' => 'تحديد كغائب',
        'view' => 'عرض',
        'record_payment' => 'تسجيل دفعة',
    ],

    // Forms
    'forms' => [
        'room' => 'اختر الغرفة',
        'practitioner' => 'اختر الممارس',
    ],

    // Messages
    'messages' => [
        'appointment_not_found' => 'الموعد غير موجود',
        'cannot_check_in' => 'لا يمكن تسجيل حضور المريض',
        'checked_in' => 'تم تسجيل الحضور',
        'checked_in_body' => 'تم تسجيل حضور :patient',
        'room_not_found' => 'الغرفة غير موجودة',
        'room_assigned' => 'تم تعيين الغرفة',
        'room_assigned_body' => 'تم تعيين :patient في :room',
        'practitioner_not_found' => 'الممارس غير موجود',
        'doctor_assigned' => 'تم تعيين الطبيب',
        'doctor_assigned_body' => 'تم تعيين :patient مع :doctor',
        'cannot_start' => 'لا يمكن بدء الجلسة',
        'session_started' => 'بدأت الجلسة',
        'session_started_body' => 'بدأت الجلسة لـ :patient',
        'cannot_mark_no_show' => 'لا يمكن التحديد كغائب',
        'marked_no_show' => 'تم التحديد كغائب',
        'marked_no_show_body' => 'تم تحديد :patient كغائب',
    ],

    // Patient Flow
    'flow' => [
        'title' => 'تدفق المرضى',
        'arriving' => 'قادمون قريباً',
        'arriving_desc' => 'خلال 30 دقيقة',
        'waiting' => 'منطقة الانتظار',
        'waiting_desc' => 'تم الحضور، بدون غرفة',
        'in_rooms' => 'في الغرف',
        'in_rooms_desc' => 'تم تعيين غرفة',
        'with_doctor' => 'مع الطبيب',
        'with_doctor_desc' => 'الجلسة جارية',
        'done' => 'انتهى',
        'done_desc' => 'مكتمل (آخر ساعتين)',
        'empty' => 'لا يوجد مرضى',
        'room_available' => 'متاحة',
        'room_empty' => 'الغرفة متاحة',
    ],

    // Modal
    'modal' => [
        'cancel' => 'إلغاء',
        'save' => 'حفظ',
        'current_doctor' => 'الطبيب الحالي',
    ],

    // Misc
    'unassigned' => 'غير معين',
    'no_room' => 'بدون غرفة',
    'unknown_patient' => 'مريض غير معروف',
];
