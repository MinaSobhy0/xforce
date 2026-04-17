<?php

return [
    'title' => 'مخطط الوجه',
    'subtitle' => 'رسم خريطة إجراءات الوجه ثلاثية الأبعاد',

    'navigation' => 'مخطط الوجه',
    'singular' => 'علامة',
    'plural' => 'علامات',

    'tabs' => [
        '3d_view' => 'مخطط ثلاثي الأبعاد',
        '2d_view' => 'تعليقات ثنائية الأبعاد',
    ],

    'viewer' => [
        'title' => 'مخطط الوجه ثلاثي الأبعاد',
        'loading' => 'جارٍ تحميل النموذج ثلاثي الأبعاد...',
        'loading_error' => 'فشل تحميل النموذج ثلاثي الأبعاد',
        'controls' => [
            'rotate' => 'اسحب للتدوير',
            'zoom' => 'قم بالتمرير للتكبير',
            'pan' => 'انقر بزر الماوس الأيمن واسحب للتحريك',
            'reset' => 'إعادة تعيين العرض',
        ],
        'edit_mode' => 'وضع التحرير',
        'view_mode' => 'وضع العرض',
        'toggle_edit' => 'تبديل وضع التحرير',
        'click_to_add' => 'انقر على الوجه لإضافة علامة',
        'drag_to_draw' => 'اسحب من العلامة لتحديد الاتجاه',
        'drawing_arrow' => 'جارٍ رسم اتجاه الحقن...',
        'press_esc' => 'اضغط ESC للإلغاء',
        'historical_markers' => 'العلامات التاريخية',
        'current_markers' => 'علامات الجلسة الحالية',
    ],

    'marker' => [
        'add' => 'إضافة علامة',
        'edit' => 'تعديل العلامة',
        'delete' => 'حذف العلامة',
        'details' => 'تفاصيل العلامة',
        'new' => 'علامة جديدة',
        'save' => 'حفظ العلامة',
        'cancel' => 'إلغاء',
    ],

    'fields' => [
        'marker_type' => 'النوع',
        'face_region' => 'المنطقة',
        'product_name' => 'المنتج',
        'units' => 'الجرعة',
        'unit_type' => 'الوحدة',
        'color' => 'اللون',
        'notes' => 'ملاحظات',
        'notes_placeholder' => 'أضف أي ملاحظات حول نقطة الحقن هذه...',
        'direction' => 'اتجاه الحقن',
        'depth' => 'العمق (مم)',
        'drag_instruction_title' => 'اسحب لرسم السهم',
        'drag_instruction_desc' => 'أغلق هذه النافذة واسحب من العلامة لتحديد اتجاه الحقن بصرياً.',
        'direction_set' => 'تم تحديد الاتجاه',
        'clear_direction' => 'مسح الاتجاه',
        'use_preset' => 'أو استخدم اتجاه محدد مسبقاً...',
        'performed_at' => 'التاريخ',
        'performed_by' => 'بواسطة',
        'appointment' => 'الموعد',
        'service' => 'الخدمة',
        'coordinates' => 'الإحداثيات',
    ],

    'regions' => [
        'forehead' => 'الجبهة',
        'glabella' => 'بين الحاجبين',
        'temples' => 'الصدغين',
        'crow_feet' => 'أقدام الغراب',
        'upper_eyelid' => 'الجفن العلوي',
        'lower_eyelid' => 'الجفن السفلي',
        'nose' => 'الأنف',
        'cheeks' => 'الخدين',
        'nasolabial' => 'الطيات الأنفية الشفوية',
        'upper_lip' => 'الشفة العليا',
        'lower_lip' => 'الشفة السفلى',
        'marionette' => 'خطوط الماريونيت',
        'chin' => 'الذقن',
        'jawline' => 'خط الفك',
        'neck' => 'الرقبة',
    ],

    'marker_types' => [
        'injection' => 'حقن',
        'filler_point' => 'نقطة الفيلر',
        'laser_spot' => 'نقطة الليزر',
        'thread_anchor' => 'مرساة الخيط',
        'marking' => 'علامة',
    ],

    'unit_types' => [
        'units' => 'وحدات',
        'ml' => 'مل',
        'cc' => 'سم مكعب',
    ],

    'filters' => [
        'title' => 'الفلاتر',
        'date_from' => 'من تاريخ',
        'date_to' => 'إلى تاريخ',
        'region' => 'المنطقة',
        'type' => 'النوع',
        'apply' => 'تطبيق الفلاتر',
        'clear' => 'مسح الفلاتر',
        'all_regions' => 'جميع المناطق',
        'all_types' => 'جميع الأنواع',
    ],

    'stats' => [
        'title' => 'الإحصائيات',
        'total_markers' => 'إجمالي العلامات',
        'total_units' => 'إجمالي الوحدات',
        'appointments' => 'المواعيد',
        'by_type' => 'حسب النوع',
        'by_region' => 'حسب المنطقة',
    ],

    'messages' => [
        'marker_added' => 'تمت إضافة العلامة بنجاح',
        'marker_updated' => 'تم تحديث العلامة بنجاح',
        'marker_deleted' => 'تم حذف العلامة بنجاح',
        'cannot_edit_historical' => 'لا يمكن تعديل علامات من المواعيد السابقة',
        'cannot_delete_historical' => 'لا يمكن حذف علامات من المواعيد السابقة',
        'no_markers' => 'لا توجد علامات',
        'no_appointment' => 'ابدأ جلسة علاج لإضافة علامات',
    ],

    'confirm' => [
        'delete_marker' => 'هل أنت متأكد أنك تريد حذف هذه العلامة؟',
    ],

    'legend' => [
        'title' => 'وسيلة الإيضاح',
        'historical' => 'تاريخي (للقراءة فقط)',
        'current' => 'الجلسة الحالية (قابل للتعديل)',
        'injection' => 'حقن/بوتوكس',
        'laser' => 'علاج الليزر',
        'filler' => 'فيلر',
        'thread' => 'شد الخيوط',
    ],

    '2d' => [
        'title' => 'مخطط الوجه ثنائي الأبعاد',
        'tools' => [
            'marker' => 'إضافة علامة',
            'text' => 'تعليق نصي',
            'arrow' => 'رسم سهم',
            'pen' => 'رسم حر (قلم)',
            'select' => 'تحديد/نقل',
            'eraser' => 'انقر للحذف',
            'color' => 'لون الرسم',
            'size' => 'حجم الخط',
            'delete' => 'حذف المحدد',
            'clear' => 'مسح الكل',
        ],
        'text_annotation' => [
            'placeholder' => 'انقر لتعديل النص',
            'font_size' => 'حجم الخط',
            'font_color' => 'لون النص',
            'bold' => 'عريض',
            'italic' => 'مائل',
        ],
        'marker_count' => ':count علامة',
        'markers_list' => 'العلامات',
        'no_markers' => 'لا توجد علامات حتى الآن. انقر على صورة الوجه لإضافة علامات.',
        'unnamed_marker' => 'علامة بدون اسم',
        'confirm_delete' => 'هل أنت متأكد أنك تريد حذف هذه العلامة؟',
        'confirm_clear' => 'هل أنت متأكد أنك تريد مسح جميع التعليقات القابلة للتعديل؟',
    ],

    'statistics' => [
        'title' => 'الإحصائيات',
        'total_markers' => 'إجمالي العلامات',
        'total_units' => 'إجمالي الوحدات',
        'appointments' => 'المواعيد',
    ],

    'views' => [
        'front' => 'أمامي',
        'left' => 'يسار',
        'right' => 'يمين',
    ],
];
