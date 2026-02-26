<?php

return [
    // Tab/Section title
    'title' => 'سجل النشاط',

    // Table columns
    'columns' => [
        'event' => 'الحدث',
        'description' => 'الوصف',
        'changed_by' => 'تم التغيير بواسطة',
        'when' => 'متى',
        'changes' => 'التغييرات',
    ],

    // Event types
    'events' => [
        'created' => 'تم الإنشاء',
        'updated' => 'تم التحديث',
        'deleted' => 'تم الحذف',
        'restored' => 'تم الاستعادة',
    ],

    // Filters
    'filters' => [
        'event_type' => 'نوع الحدث',
        'changed_by' => 'تم التغيير بواسطة',
        'from_date' => 'من تاريخ',
        'to_date' => 'إلى تاريخ',
        'from' => 'من',
        'to' => 'إلى',
    ],

    // View modal
    'view' => [
        'title' => 'تفاصيل النشاط',
        'event_info' => 'معلومات الحدث',
        'changes' => 'التغييرات التي تمت',
    ],

    // Empty state
    'empty' => [
        'heading' => 'لا يوجد نشاط مسجل',
        'description' => 'سيظهر النشاط هنا عند إجراء تغييرات على هذا السجل.',
    ],

    // System user
    'system' => 'النظام',

    // Change display
    'changes_display' => [
        'old_value' => 'القيمة القديمة',
        'new_value' => 'القيمة الجديدة',
        'field' => 'الحقل',
        'no_changes' => 'لا توجد تغييرات مسجلة',
    ],
];
