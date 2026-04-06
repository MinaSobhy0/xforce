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
    'timer_paused' => 'المؤقت متوقف مؤقتاً',

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

    // Kanban State
    'kanban_state' => [
        'normal' => 'قيد التنفيذ',
        'blocked' => 'محظور',
        'done' => 'جاهز',
    ],

    // Timesheet Status
    'timesheet_status' => [
        'draft' => 'مسودة',
        'submitted' => 'مُرسل',
        'approved' => 'موافق عليه',
        'rejected' => 'مرفوض',
    ],

    // Timesheet Submission
    'timesheet_submission' => 'تقديم جدول الوقت',
    'timesheet_submissions' => 'تقديمات جداول الوقت',
    'timesheet' => [
        'week_of' => 'أسبوع :date',
        'total_hours' => 'إجمالي الساعات',
        'billable_hours' => 'ساعات قابلة للفوترة',
        'non_billable_hours' => 'ساعات غير قابلة للفوترة',
        'days_worked' => 'أيام العمل',
        'submit_for_approval' => 'إرسال للموافقة',
        'approve' => 'موافقة',
        'reject' => 'رفض',
        'rejection_reason' => 'سبب الرفض',
        'approved_by' => 'موافق عليه من',
        'submitted_at' => 'تاريخ الإرسال',
        'approved_at' => 'تاريخ الموافقة',
        'pending_approval' => 'في انتظار الموافقة',
        'no_submissions' => 'لا توجد تقديمات بعد',
        'submit_confirmation' => 'هل أنت متأكد أنك تريد إرسال هذا الجدول للموافقة؟',
    ],

    // Privacy
    'privacy' => [
        'label' => 'الخصوصية',
        'employees' => 'جميع الموظفين',
        'followers' => 'المتابعون فقط',
        'portal' => 'مستخدمو البوابة',
    ],

    // Timer Actions
    'timer' => [
        'start' => 'بدء المؤقت',
        'stop' => 'إيقاف المؤقت',
        'pause' => 'إيقاف مؤقت',
        'resume' => 'استئناف المؤقت',
        'elapsed' => 'الوقت المنقضي',
    ],

    // Task fields
    'task' => [
        'remaining_hours' => 'الساعات المتبقية',
        'effective_hours' => 'الساعات الفعلية',
        'kanban_state' => 'حالة كانبان',
        'mark_blocked' => 'تعيين كمحظور',
        'mark_ready' => 'تعيين كجاهز',
        'reset_state' => 'إعادة تعيين الحالة',
    ],

    // Member fields
    'member' => [
        'hourly_rate' => 'سعر الساعة',
    ],
];
