<?php

return [
    // Navigation & Pages
    'connections' => 'اتصالات Odoo',
    'connection' => 'اتصال Odoo',
    'entity_mappings' => 'تعيينات الكيانات',
    'entity_mapping' => 'تعيين الكيان',
    'field_mappings' => 'تعيينات الحقول',
    'field_mapping' => 'تعيين الحقل',
    'pages' => [
        'dashboard' => 'مزامنة Odoo',
        'conflicts' => 'تعارضات المزامنة',
    ],

    // Sections
    'sections' => [
        'connection_details' => 'تفاصيل الاتصال',
        'authentication' => 'المصادقة',
        'settings' => 'الإعدادات',
        'entity_mapping' => 'تعيين الكيان',
        'field_mapping' => 'تعيين الحقل',
        'transform_config' => 'إعدادات التحويل',
        'filters' => 'الفلاتر',
        'date_filter' => 'فلتر التاريخ',
        'recent_activity' => 'النشاط الأخير',
    ],

    // Fields
    'fields' => [
        'name' => 'الاسم',
        'code' => 'الرمز',
        'host' => 'رابط الخادم',
        'port' => 'المنفذ',
        'database' => 'اسم قاعدة البيانات',
        'protocol' => 'البروتوكول',
        'username' => 'اسم المستخدم',
        'password' => 'كلمة المرور',
        'api_key' => 'مفتاح API',
        'use_ssl' => 'استخدام SSL',
        'timeout' => 'مهلة الاتصال',
        'rate_limit' => 'حد المعدل',
        'timezone' => 'المنطقة الزمنية',
        'is_active' => 'نشط',
        'is_default' => 'الاتصال الافتراضي',
        'last_connected' => 'آخر اتصال',
        'last_sync' => 'آخر مزامنة',
        'mappings' => 'التعيينات',
        'local_model' => 'النموذج المحلي',
        'local_table' => 'الجدول المحلي',
        'odoo_model' => 'نموذج Odoo',
        'sync_direction' => 'اتجاه المزامنة',
        'sync_frequency' => 'تكرار المزامنة',
        'conflict_resolution' => 'حل التعارض',
        'batch_size' => 'حجم الدفعة',
        'priority' => 'الأولوية',
        'filter_conditions' => 'شروط الفلترة',
        'entity' => 'الكيان',
        'records' => 'السجلات',
        'conflicts' => 'التعارضات',
        'sync_type' => 'نوع المزامنة',
        'direction' => 'الاتجاه',
        'status' => 'الحالة',
        'processed' => 'معالج',
        'created' => 'تم الإنشاء',
        'updated' => 'تم التحديث',
        'failed' => 'فشل',
        'started_at' => 'وقت البدء',
        'duration' => 'المدة',
        'triggered_by' => 'بواسطة',
        'conflict_type' => 'نوع التعارض',
        'type' => 'النوع',
        'local_id' => 'المعرف المحلي',
        'odoo_id' => 'معرف Odoo',
        'resolution' => 'الحل',
        'resolved_by' => 'تم الحل بواسطة',
        'detected_at' => 'وقت الاكتشاف',
        'detected' => 'مكتشف',
        'notes' => 'ملاحظات',
        'local_field' => 'الحقل المحلي',
        'odoo_field' => 'حقل Odoo',
        'transform_type' => 'نوع التحويل',
        'is_required' => 'مطلوب',
        'is_key_field' => 'حقل مفتاحي',
        'default_value' => 'القيمة الافتراضية',
        'sort_order' => 'ترتيب الفرز',
        'connection' => 'الاتصال',
        'sync_date_field' => 'حقل التاريخ',
        'sync_from_date' => 'من تاريخ',
        'sync_to_date' => 'إلى تاريخ',
    ],

    // Helpers
    'helpers' => [
        'code' => 'معرف فريد لهذا الاتصال (يتم إنشاؤه تلقائيًا إذا كان فارغًا)',
        'api_key' => 'مفتاح API اختياري للمصادقة (Odoo 14+)',
        'is_default' => 'استخدم هذا الاتصال كافتراضي لعمليات المزامنة',
        'priority' => 'الأرقام الأقل تتم مزامنتها أولاً (مثل المستخدمين قبل الموظفين)',
        'filter_conditions' => 'فلاتر نطاق Odoo (مثل [["active", "=", true]])',
        'date_filter_description' => 'فلترة السجلات حسب نطاق التاريخ عند المزامنة من Odoo',
        'sync_date_field' => 'حدد حقل التاريخ في Odoo للفلترة',
        'sync_from_date' => 'مزامنة السجلات التي تاريخها >= هذه القيمة فقط',
        'sync_to_date' => 'مزامنة السجلات التي تاريخها <= هذه القيمة فقط',
    ],

    // Units
    'units' => [
        'seconds' => 'ثانية',
        'per_minute' => 'في الدقيقة',
    ],

    // Actions
    'actions' => [
        'test_connection' => 'اختبار الاتصال',
        'sync_all' => 'مزامنة الكل',
        'sync' => 'مزامنة',
        'full_sync' => 'مزامنة كاملة',
        'create_connection' => 'إنشاء اتصال',
        'view_errors' => 'عرض الأخطاء',
        'view_diff' => 'عرض الاختلافات',
        'keep_local' => 'الاحتفاظ بالمحلي',
        'keep_odoo' => 'الاحتفاظ بـ Odoo',
        'dismiss' => 'تجاهل',
        'dismiss_all' => 'تجاهل الكل',
        'resolve' => 'حل',
        'manage_fields' => 'إدارة الحقول',
        'auto_generate' => 'إنشاء تلقائي',
        'push_to_odoo' => 'إرسال إلى أودو',
    ],

    // Messages
    'messages' => [
        'connection_success' => 'تم الاتصال بنجاح!',
        'connection_failed' => 'فشل الاتصال',
        'sync_queued' => 'تم إضافة مهمة المزامنة بنجاح',
        'full_sync_warning' => 'قد تستغرق المزامنة الكاملة وقتًا حسب حجم البيانات',
        'conflict_resolved' => 'تم حل التعارض بنجاح',
        'conflict_dismissed' => 'تم تجاهل التعارض',
        'conflicts_dismissed' => 'تم تجاهل التعارضات المحددة',
        'push_unlinked_confirm' => 'هل تريد إرسال :count سجل بدون odoo_id إلى أودو؟ سيتم إنشاؤها وربطها تلقائيًا عبر odoo_id.',
        'push_bulk_done' => 'انتهى الإرسال إلى أودو',
        'push_bulk_body' => 'تم إرسال :pushed، تم تخطي :skipped، فشل :failed.',
        'push_skipped_reason' => 'تم تخطي السجل :id — الحقل :field (المعرف :related_id) غير مرتبط بأودو بعد؛ يجب إرساله أولًا.',
        'push_failed' => 'فشل الإرسال إلى أودو',
        'no_mapping' => 'لا يوجد تعيين نشط يسمح بالتصدير.',
    ],

    // Modals
    'modals' => [
        'sync_all_title' => 'مزامنة جميع الكيانات',
        'sync_all_description' => 'سيتم إضافة مهام مزامنة لجميع تعيينات الكيانات النشطة. هل تريد المتابعة؟',
        'full_sync_title' => 'مزامنة كاملة',
        'full_sync_description' => 'سيتم إجراء مزامنة كاملة متجاهلة العلامات المائية. قد يستغرق هذا وقتًا أطول. هل تريد المتابعة؟',
        'conflict_diff_title' => 'تفاصيل التعارض',
    ],

    // Labels
    'labels' => [
        'all_entities' => 'جميع الكيانات',
        'records' => 'سجلات',
        'local_data' => 'البيانات المحلية',
        'odoo_data' => 'بيانات Odoo',
        'changed_fields' => 'الحقول المتغيرة',
        'record_deleted' => 'تم حذف السجل',
    ],

    // Empty States
    'empty' => [
        'no_connections' => 'لا توجد اتصالات Odoo',
        'no_connections_description' => 'ابدأ بإنشاء أول اتصال Odoo.',
        'no_recent_activity' => 'لا يوجد نشاط مزامنة حديث',
        'no_conflicts' => 'لا توجد تعارضات معلقة',
        'no_conflicts_description' => 'تم حل جميع تعارضات المزامنة.',
        'no_errors' => 'لا توجد أخطاء للعرض',
    ],

    // Stats
    'stats' => [
        'active_connections' => 'الاتصالات النشطة',
        'connections_description' => 'خوادم Odoo المكونة',
        'synced_today' => 'مزامنة اليوم',
        'synced_description' => 'السجلات المعالجة',
        'total_synced' => 'إجمالي المزامنة',
        'total_description' => 'السجلات المرتبطة بـ Odoo',
        'pending_conflicts' => 'تعارضات معلقة',
        'conflicts_description' => 'تتطلب حل يدوي',
        'failed_today' => 'فشل اليوم',
        'failed_description' => 'سجلات بها أخطاء',
        'last_sync' => 'آخر مزامنة',
        'no_sync_yet' => 'لم تكتمل أي مزامنة بعد',
    ],

    // Widgets
    'widgets' => [
        'pending_conflicts' => 'التعارضات المعلقة',
    ],
];
