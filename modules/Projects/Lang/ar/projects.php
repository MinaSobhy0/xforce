<?php

return [
    // General
    'project' => 'مشروع',
    'projects' => 'المشاريع',
    'tag' => 'علامة',
    'tags' => 'العلامات',
    'time_entry' => 'إدخال وقت',
    'time_entries' => 'إدخالات الوقت',
    'dashboard' => 'لوحة المشاريع',
    'kanban_board' => 'لوحة كانبان',
    'gantt_view' => 'عرض جانت',

    // Sections
    'sections' => [
        'project_details' => 'تفاصيل المشروع',
        'dates' => 'التواريخ',
        'budget' => 'الميزانية',
        'settings' => 'إعدادات المشروع',
        'description' => 'الوصف',
        'statistics' => 'الإحصائيات',
    ],

    // Fields
    'fields' => [
        'code' => 'الكود',
        'name' => 'الاسم',
        'name_en' => 'الاسم (إنجليزي)',
        'name_ar' => 'الاسم (عربي)',
        'description' => 'الوصف',
        'description_en' => 'الوصف (إنجليزي)',
        'description_ar' => 'الوصف (عربي)',
        'branch' => 'الفرع',
        'manager' => 'المدير',
        'status' => 'الحالة',
        'priority' => 'الأولوية',
        'color' => 'اللون',
        'start_date' => 'تاريخ البدء',
        'end_date' => 'تاريخ الانتهاء',
        'deadline' => 'الموعد النهائي',
        'target_date' => 'التاريخ المستهدف',
        'budget' => 'الميزانية',
        'actual_cost' => 'التكلفة الفعلية',
        'progress' => 'التقدم',
        'total_tasks' => 'إجمالي المهام',
        'tasks' => 'المهام',
        'logged_hours' => 'الساعات المسجلة',
        'allow_timesheets' => 'السماح بتسجيل الوقت',
        'is_template' => 'قالب',
        'is_active' => 'نشط',
        'created' => 'تاريخ الإنشاء',
        'template' => 'قالب',
        'status_type' => 'نوع الحالة',
        'sort_order' => 'الترتيب',
        'fold_by_default' => 'طي افتراضياً',
        'is_final' => 'مرحلة نهائية',
        'user' => 'المستخدم',
        'role' => 'الدور',
        'email' => 'البريد الإلكتروني',
        'date' => 'التاريخ',
        'billable' => 'قابل للفوترة',
        'hourly_rate' => 'سعر الساعة',
        'amount' => 'المبلغ',
        'timer' => 'المؤقت',
    ],

    // Helpers
    'helpers' => [
        'is_template' => 'يمكن نسخ القوالب لإنشاء مشاريع جديدة',
        'is_final' => 'المهام في هذه المرحلة تُعتبر مكتملة',
    ],

    // Actions
    'actions' => [
        'view_kanban' => 'لوحة كانبان',
        'clone' => 'نسخ',
        'activate' => 'تفعيل',
        'complete' => 'إكمال',
        'reopen' => 'إعادة فتح',
        'change_role' => 'تغيير الدور',
        'stop_timer' => 'إيقاف المؤقت',
        'start_timer' => 'بدء المؤقت',
    ],

    // Filters
    'filters' => [
        'templates' => 'القوالب',
        'active' => 'نشط',
        'this_week' => 'هذا الأسبوع',
        'this_month' => 'هذا الشهر',
        'from' => 'من',
        'until' => 'إلى',
    ],

    // Messages
    'messages' => [
        'select_project' => 'اختر مشروعاً للعرض',
        'no_tasks' => 'لا توجد مهام في هذا المشروع بعد',
        'timer_stopped' => 'تم إيقاف المؤقت بنجاح',
    ],

    // Timer
    'timer_running' => 'المؤقت يعمل',

    // Stats
    'stats' => [
        'active_projects' => 'المشاريع النشطة',
        'active_projects_desc' => 'قيد التنفيذ حالياً',
        'open_tasks' => 'المهام المفتوحة',
        'open_tasks_desc' => 'عبر جميع المشاريع',
        'my_tasks' => 'مهامي',
        'my_tasks_desc' => 'المُسندة إليك',
        'overdue_tasks' => 'متأخرة',
        'overdue_tasks_desc' => 'تجاوزت الموعد النهائي',
        'hours_this_week' => 'ساعات هذا الأسبوع',
        'hours_this_week_desc' => 'وقتك المسجل',
    ],
];
