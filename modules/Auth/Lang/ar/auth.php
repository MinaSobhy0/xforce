<?php

return [
    // Module info
    'module_name' => 'المصادقة',
    'module_description' => 'إدارة مصادقة المستخدمين والأدوار والصلاحيات',

    // Navigation
    'navigation' => [
        'users' => 'المستخدمين',
        'roles' => 'الأدوار',
        'access_policies' => 'سياسات الوصول',
    ],

    // Labels
    'labels' => [
        'user' => 'مستخدم',
        'users' => 'المستخدمين',
        'role' => 'دور',
        'roles' => 'الأدوار',
        'access_policy' => 'سياسة الوصول',
        'access_policies' => 'سياسات الوصول',
    ],

    // Sections
    'sections' => [
        'user_details' => 'تفاصيل المستخدم',
        'policy_details' => 'تفاصيل السياسة',
        'role_details' => 'تفاصيل الدور',
        'permissions' => 'الصلاحيات',
        'permissions_description' => 'حدد الصلاحيات التي يجب أن يمتلكها هذا الدور لكل مورد',
        'domain_filter' => 'فلتر النطاق (قواعد السجلات)',
    ],

    // Permissions actions
    'permissions' => [
        'view' => 'عرض',
        'create' => 'إنشاء',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'export' => 'تصدير',
        'import' => 'استيراد',
    ],

    // Permission groups
    'permission_groups' => [
        'patients_booking' => 'المرضى والحجوزات',
        'services_packages' => 'الخدمات والباقات',
        'billing_payments' => 'الفواتير والمدفوعات',
        'inventory_products' => 'المخزون والمنتجات',
        'equipment_assets' => 'المعدات والأصول',
        'staff_hr' => 'الموظفين والموارد البشرية',
        'attendance_timeoff' => 'الحضور والإجازات',
        'marketing_loyalty' => 'التسويق والولاء',
        'memberships_giftcards' => 'العضويات وبطاقات الهدايا',
        'accounting' => 'المحاسبة',
        'settings_admin' => 'الإعدادات والإدارة',
    ],

    // Resources for permission management
    'resources' => [
        // Patients & Booking
        'patients' => 'المرضى',
        'appointments' => 'المواعيد',
        'visits' => 'الزيارات',
        'waitlist' => 'قائمة الانتظار',
        'treatment_plans' => 'خطط العلاج',
        'prescriptions' => 'الوصفات الطبية',

        // Services & Packages
        'services' => 'الخدمات',
        'service_categories' => 'فئات الخدمات',
        'packages' => 'الباقات',
        'consent_templates' => 'قوالب الموافقة',
        'parameter_templates' => 'قوالب المعايير',

        // Billing & Payments
        'invoices' => 'الفواتير',
        'payments' => 'المدفوعات',
        'tax_rates' => 'معدلات الضريبة',

        // Inventory & Products
        'products' => 'المنتجات',
        'product_categories' => 'فئات المنتجات',
        'suppliers' => 'الموردين',
        'purchase_orders' => 'أوامر الشراء',
        'vendor_bills' => 'فواتير الموردين',
        'stock_movements' => 'حركات المخزون',
        'stock_locations' => 'مواقع المخزون',
        'stock_transfers' => 'تحويلات المخزون',
        'inventory_adjustments' => 'تسويات المخزون',
        'uoms' => 'وحدات القياس',

        // Equipment & Assets
        'equipment' => 'المعدات',
        'equipment_parameter_templates' => 'معايير المعدات',
        'assets' => 'الأصول',
        'asset_types' => 'أنواع الأصول',

        // Staff & HR
        'staff' => 'الموظفين',
        'commission_plans' => 'خطط العمولة',
        'payroll' => 'الرواتب',
        'payslips' => 'كشوف الرواتب',
        'salary_structures' => 'هياكل الرواتب',
        'salary_rules' => 'قواعد الرواتب',

        // Attendance & Time Off
        'attendance' => 'الحضور',
        'attendance_rules' => 'قواعد الحضور',
        'attendance_violations' => 'مخالفات الحضور',
        'time_off_types' => 'أنواع الإجازات',
        'time_off_allocations' => 'رصيد الإجازات',
        'practitioner_time_off' => 'إجازات الممارسين',

        // Marketing & Loyalty
        'campaigns' => 'الحملات',
        'message_templates' => 'قوالب الرسائل',
        'automation_rules' => 'قواعد الأتمتة',
        'notification_logs' => 'سجل الإشعارات',
        'loyalty_rules' => 'قواعد الولاء',
        'loyalty_transactions' => 'معاملات الولاء',
        'referral_programs' => 'برامج الإحالة',

        // Memberships & Gift Cards
        'memberships' => 'العضويات',
        'gift_cards' => 'بطاقات الهدايا',
        'gift_card_templates' => 'قوالب بطاقات الهدايا',

        // Accounting
        'chart_of_accounts' => 'دليل الحسابات',
        'journal_entries' => 'القيود المحاسبية',
        'journals' => 'اليوميات',
        'fiscal_periods' => 'الفترات المالية',

        // Settings & Administration
        'users' => 'المستخدمين',
        'roles' => 'الأدوار',
        'access_policies' => 'سياسات الوصول',
        'branches' => 'الفروع',
        'rooms' => 'الغرف',
        'work_schedules' => 'جداول العمل',
        'booking_rules' => 'قواعد الحجز',
        'blackout_dates' => 'تواريخ الحظر',
        'medicine_catalogs' => 'دليل الأدوية',
        'settings' => 'الإعدادات',
        'reports' => 'التقارير',
    ],

    // Fields
    'fields' => [
        'name' => 'الاسم',
        'display_name' => 'اسم العرض',
        'description' => 'الوصف',
        'level' => 'المستوى',
        'is_active' => 'نشط',
        'users_count' => 'المستخدمين',
        'permissions_count' => 'الصلاحيات',
        'system' => 'النظام',
        'active' => 'نشط',
        'role' => 'الدور',
        'model_type' => 'نوع النموذج',
        'apply_to_all_roles' => 'تطبيق على جميع الأدوار',
        'priority' => 'الأولوية',
        'perm_read' => 'قراءة',
        'perm_create' => 'إنشاء',
        'perm_update' => 'تعديل',
        'perm_delete' => 'حذف',
        'conditions' => 'الشروط',
        'field' => 'الحقل',
        'operator' => 'العامل',
        'value' => 'القيمة',
        'read' => 'قراءة',
        'create' => 'إنشاء',
        'update' => 'تعديل',
        'delete' => 'حذف',
    ],

    // Helpers
    'helpers' => [
        'apply_to_all_roles' => 'عند التفعيل، تُطبق هذه السياسة على جميع الأدوار بغض النظر عن اختيار الدور',
        'priority' => 'رقم أقل = أولوية أعلى. يتم تقييم السياسات حسب الأولوية.',
        'placeholders' => 'استخدم {user.id}, {user.branch_id}, {user.tenant_id}, {today}, {now}',
        'role_level' => 'مستوى أعلى = صلاحيات أكثر. يُستخدم لوراثة الصلاحيات.',
    ],

    // Actions
    'actions' => [
        'add_condition' => 'إضافة شرط',
    ],

    // Other
    'all_roles' => 'جميع الأدوار',

    // Users
    'user' => 'مستخدم',
    'users' => 'المستخدمين',
    'user_name' => 'الاسم',
    'user_email' => 'البريد الإلكتروني',
    'user_phone' => 'الهاتف',
    'user_password' => 'كلمة المرور',
    'user_confirm_password' => 'تأكيد كلمة المرور',
    'user_avatar' => 'الصورة الشخصية',
    'user_role' => 'الدور',
    'user_roles' => 'الأدوار',
    'user_status' => 'الحالة',
    'user_last_login' => 'آخر تسجيل دخول',
    'user_created' => 'تاريخ الإنشاء',

    // User Statuses
    'status_active' => 'نشط',
    'status_inactive' => 'غير نشط',
    'status_suspended' => 'موقوف',
    'status_pending' => 'قيد الانتظار',

    // Roles
    'role' => 'دور',
    'roles' => 'الأدوار',
    'role_name' => 'اسم الدور',
    'role_permissions' => 'الصلاحيات',
    'role_users' => 'المستخدمين بهذا الدور',
    'system_role' => 'دور النظام',

    // Permissions
    'permission' => 'صلاحية',
    'permissions' => 'الصلاحيات',
    'permission_name' => 'اسم الصلاحية',
    'permission_module' => 'الوحدة',
    'grant_permission' => 'منح الصلاحية',
    'revoke_permission' => 'إلغاء الصلاحية',

    // Profile
    'profile' => 'الملف الشخصي',
    'my_profile' => 'ملفي الشخصي',
    'edit_profile' => 'تعديل الملف الشخصي',
    'change_password' => 'تغيير كلمة المرور',
    'current_password' => 'كلمة المرور الحالية',
    'new_password' => 'كلمة المرور الجديدة',
    'confirm_new_password' => 'تأكيد كلمة المرور الجديدة',
    'password_changed' => 'تم تغيير كلمة المرور بنجاح',
    'profile_updated' => 'تم تحديث الملف الشخصي بنجاح',

    // Two Factor Authentication
    'two_factor' => 'المصادقة الثنائية',
    'two_factor_setup' => 'إعداد المصادقة الثنائية',
    'enable_2fa' => 'تفعيل المصادقة الثنائية',
    'disable_2fa' => 'إلغاء المصادقة الثنائية',
    'scan_qr_code' => 'امسح رمز QR بتطبيق المصادقة',
    'enter_code' => 'أدخل رمز التحقق',
    'backup_codes' => 'رموز النسخ الاحتياطي',
    'backup_codes_warning' => 'احفظ هذه الرموز في مكان آمن. كل رمز يمكن استخدامه مرة واحدة فقط.',
    '2fa_enabled' => 'تم تفعيل المصادقة الثنائية',
    '2fa_disabled' => 'تم إلغاء المصادقة الثنائية',

    // Login / Authentication
    'login' => 'تسجيل الدخول',
    'logout' => 'تسجيل الخروج',
    'sign_in' => 'دخول',
    'sign_out' => 'خروج',
    'remember_me' => 'تذكرني',
    'forgot_password' => 'نسيت كلمة المرور؟',
    'reset_password' => 'إعادة تعيين كلمة المرور',
    'send_reset_link' => 'إرسال رابط إعادة التعيين',
    'login_failed' => 'بيانات الدخول غير صحيحة',
    'account_locked' => 'تم قفل حسابك بسبب كثرة المحاولات الفاشلة',
    'session_expired' => 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مجدداً.',

    // Messages
    'user_created' => 'تم إنشاء المستخدم بنجاح',
    'user_updated' => 'تم تحديث المستخدم بنجاح',
    'user_deleted' => 'تم حذف المستخدم بنجاح',
    'role_created' => 'تم إنشاء الدور بنجاح',
    'role_updated' => 'تم تحديث الدور بنجاح',
    'role_deleted' => 'تم حذف الدور بنجاح',
    'cannot_delete_system_role' => 'لا يمكن حذف أدوار النظام',
    'cannot_delete_own_account' => 'لا يمكنك حذف حسابك الخاص',

    // Impersonation
    'impersonate' => 'انتحال الهوية',
    'stop_impersonating' => 'إيقاف الانتحال',
    'impersonating_user' => 'أنت تنتحل هوية :name',

    // Access Policies
    'access_policy' => 'سياسة الوصول',
    'access_policies' => 'سياسات الوصول',
    'policy_details' => 'تفاصيل السياسة',
    'policy_name' => 'اسم السياسة',
    'model_type' => 'نوع النموذج',
    'model_type_help' => 'اسم الفئة الكامل للنموذج (مثال: Modules\\Patients\\Models\\Patient)',
    'description' => 'الوصف',
    'apply_to_all_roles' => 'تطبيق على جميع الأدوار',
    'apply_to_all_roles_help' => 'عند التفعيل، تُطبق هذه السياسة على جميع الأدوار بغض النظر عن اختيار الدور',
    'priority' => 'الأولوية',
    'priority_help' => 'رقم أقل = أولوية أعلى. يتم تقييم السياسات حسب ترتيب الأولوية.',
    'active' => 'نشط',
    'created_at' => 'تاريخ الإنشاء',
    'duplicate' => 'نسخ',
    'all_roles' => 'جميع الأدوار',

    // Permissions in Access Policies
    'perm_read' => 'قراءة',
    'perm_create' => 'إنشاء',
    'perm_update' => 'تحديث',
    'perm_delete' => 'حذف',

    // Domain Filter
    'domain_filter' => 'قواعد تصفية السجلات',
    'field' => 'الحقل',
    'operator' => 'المشغل',
    'value' => 'القيمة',
    'value_placeholders' => 'استخدم {user.id}, {user.branch_id}, {user.tenant_id}, {today}, {now}',
    'add_condition' => 'إضافة شرط',
    'domain_filter_help' => 'حدد الشروط لتصفية السجلات. سيرى المستخدمون فقط السجلات التي تطابق جميع الشروط.',

    // Branch Roles
    'branch_role' => 'دور الفرع',
    'branch_roles' => 'أدوار الفروع',
    'assign_branch_role' => 'تعيين دور للفرع',
    'primary_branch' => 'الفرع الرئيسي',
    'branch_access' => 'صلاحيات الفروع',

    // Branch Role Fields
    'fields' => [
        'name' => 'الاسم',
        'model_type' => 'نوع النموذج',
        'role' => 'الدور',
        'apply_to_all_roles' => 'تطبيق على جميع الأدوار',
        'priority' => 'الأولوية',
        'description' => 'الوصف',
        'is_active' => 'نشط',
        'perm_read' => 'قراءة',
        'perm_create' => 'إنشاء',
        'perm_update' => 'تحديث',
        'perm_delete' => 'حذف',
        'conditions' => 'الشروط',
        'field' => 'الحقل',
        'operator' => 'المشغل',
        'value' => 'القيمة',
        'read' => 'قراءة',
        'create' => 'إنشاء',
        'update' => 'تحديث',
        'delete' => 'حذف',
        'active' => 'نشط',
        'branch' => 'الفرع',
        'is_primary' => 'رئيسي',
        'expires_at' => 'تنتهي في',
        'assigned_at' => 'تم التعيين في',
    ],

    // Branch Role Helpers
    'helpers' => [
        'apply_to_all_roles' => 'عند التفعيل، تُطبق هذه السياسة على جميع الأدوار',
        'priority' => 'رقم أقل = أولوية أعلى',
        'placeholders' => 'استخدم {user.id}, {user.branch_id}, {user.tenant_id}, {today}, {now}',
        'primary_branch' => 'الفرع الرئيسي هو الفرع الافتراضي لهذا المستخدم',
        'expires_at' => 'اتركه فارغاً للوصول الدائم',
    ],

    // Branch Role Actions
    'actions' => [
        'add_condition' => 'إضافة شرط',
        'assign_branch' => 'تعيين فرع',
        'make_primary' => 'جعله رئيسي',
        'activate' => 'تفعيل',
        'deactivate' => 'إلغاء التفعيل',
        'grant_all' => 'منح جميع الصلاحيات',
        'revoke_all' => 'إلغاء جميع الصلاحيات',
    ],

    // Branch Role Messages
    'messages' => [
        'primary_branch_set' => 'تم تعيين الفرع الرئيسي',
        'branch_access_activated' => 'تم تفعيل صلاحية الفرع',
        'branch_access_deactivated' => 'تم إلغاء تفعيل صلاحية الفرع',
        'branch_assigned' => 'تم تعيين الفرع للمستخدم',
        'branch_removed' => 'تم إزالة صلاحية الفرع',
    ],

    // إحصائيات
    'stats' => [
        'total_users' => 'إجمالي المستخدمين',
        'all_registered' => 'جميع المستخدمين المسجلين',
        'active_users' => 'المستخدمين النشطين',
        'of_total' => 'من الإجمالي',
        'email_verified' => 'بريد مُفعّل',
        'verified' => 'مُفعّل',
        '2fa_enabled' => 'المصادقة الثنائية',
        'secured' => 'مؤمّن',
        'recent_logins' => 'تسجيلات دخول حديثة',
        'past_days' => 'خلال :days أيام',
    ],

    // مورد المستخدم
    'user_resource' => [
        // التبويبات
        'tabs' => [
            'user_information' => 'معلومات المستخدم',
            'basic_information' => 'المعلومات الأساسية',
            'account_settings' => 'إعدادات الحساب',
            'profile' => 'الملف الشخصي',
            'two_factor' => 'المصادقة الثنائية',
        ],
        // الحقول
        'first_name' => 'الاسم الأول',
        'last_name' => 'اسم العائلة',
        'email' => 'البريد الإلكتروني',
        'username' => 'اسم المستخدم',
        'phone' => 'رقم الهاتف',
        'date_of_birth' => 'تاريخ الميلاد',
        'gender' => 'الجنس',
        'address' => 'العنوان',
        'password' => 'كلمة المرور',
        'confirm_password' => 'تأكيد كلمة المرور',
        'status' => 'الحالة',
        'email_verified' => 'البريد مُفعّل',
        'must_change_password' => 'يجب تغيير كلمة المرور',
        'must_change_password_help' => 'إجبار المستخدم على تغيير كلمة المرور عند تسجيل الدخول التالي',
        'roles' => 'الأدوار',
        'allowed_branches' => 'الفروع المسموحة',
        'allowed_branches_help' => 'اختر الفروع التي يمكن لهذا المستخدم الوصول إليها.',
        'last_login' => 'آخر تسجيل دخول',
        'profile_picture' => 'الصورة الشخصية',
        'job_title' => 'المسمى الوظيفي',
        'department' => 'القسم',
        'timezone' => 'المنطقة الزمنية',
        'language' => 'اللغة',
        'biography' => 'السيرة الذاتية',
        'enable_2fa' => 'تفعيل المصادقة الثنائية',
        'enable_2fa_help' => 'إجبار المستخدم على استخدام المصادقة الثنائية',
        'backup_codes' => 'أكواد الاحتياط',
        'backup_codes_help' => 'أكواد احتياطية مفصولة بفاصلة',
        '2fa_confirmed_at' => 'تاريخ تأكيد المصادقة الثنائية',
        // خيارات الجنس
        'male' => 'ذكر',
        'female' => 'أنثى',
        'other' => 'آخر',
        // اللغات
        'arabic' => 'العربية',
        'english' => 'الإنجليزية',
        // أعمدة الجدول
        'avatar' => 'الصورة',
        'name' => 'الاسم',
        'verified' => 'مُفعّل',
        '2fa' => 'المصادقة الثنائية',
        'created' => 'تاريخ الإنشاء',
        // الفلاتر
        'two_factor_auth' => 'المصادقة الثنائية',
        'inactive_users' => 'المستخدمين غير النشطين',
        'role' => 'الدور',
        // الإجراءات
        'login_as_user' => 'تسجيل الدخول كمستخدم',
        'reset_password' => 'إعادة تعيين كلمة المرور',
        'new_password' => 'كلمة المرور الجديدة',
        'activate' => 'تفعيل',
        'deactivate' => 'إلغاء التفعيل',
        'force_password_change' => 'إجبار تغيير كلمة المرور',
        // أقسام المعلومات
        'user_profile' => 'الملف الشخصي',
        'account_status' => 'حالة الحساب',
        'role_permissions' => 'الأدوار والصلاحيات',
        '2fa_enabled' => 'المصادقة الثنائية مُفعّلة',
    ],
];
