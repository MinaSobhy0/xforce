<?php

return [
    // General
    'knowledge_base' => 'قاعدة المعرفة',
    'help' => 'المساعدة',
    'help_center' => 'مركز المساعدة',
    'article' => 'مقال',
    'articles' => 'المقالات',
    'category' => 'التصنيف',
    'categories' => 'التصنيفات',
    'guide' => 'دليل',
    'guides' => 'الأدلة',

    // Navigation
    'help_for_screen' => 'مساعدة لـ :screen',
    'start_guide' => 'بدء الدليل التفاعلي',
    'view_help_articles' => 'عرض مقالات المساعدة',
    'browse_knowledge_base' => 'تصفح قاعدة المعرفة',
    'browse_all_articles' => 'تصفح جميع المقالات',
    'no_help_for_screen' => 'لا توجد مساعدة محددة لهذه الشاشة.',

    // Knowledge Base Page
    'how_can_we_help' => 'كيف يمكننا مساعدتك؟',
    'search_description' => 'ابحث في قاعدة المعرفة للحصول على إجابات لأسئلتك.',
    'search_placeholder' => 'البحث عن مقالات...',
    'featured_articles' => 'المقالات المميزة',
    'browse_by_category' => 'تصفح حسب التصنيف',
    'back_to_categories' => 'العودة إلى التصنيفات',
    'articles_count' => ':count مقال|:count مقالات',
    'no_articles_in_category' => 'لا توجد مقالات في هذا التصنيف.',
    'no_content_yet' => 'لا يوجد محتوى مساعدة حتى الآن',
    'no_content_description' => 'سيتم إضافة مقالات وأدلة المساعدة قريباً.',

    // Article View
    'views' => 'مشاهدة',
    'found_helpful' => 'وجدوا هذا مفيداً',
    'was_this_helpful' => 'هل كان هذا المقال مفيداً؟',
    'feedback_description' => 'تساعدنا ملاحظاتك في تحسين وثائقنا.',
    'yes_helpful' => 'نعم، كان مفيداً',
    'not_helpful' => 'لا، لم يكن مفيداً',
    'yes' => 'نعم',
    'no' => 'لا',
    'back' => 'رجوع',
    'related_articles' => 'مقالات ذات صلة',
    'featured' => 'مميز',
    'article_not_found' => 'المقال غير موجود',
    'article_not_found_description' => 'المقال الذي تبحث عنه غير موجود أو تم حذفه.',
    'back_to_knowledge_base' => 'العودة إلى قاعدة المعرفة',

    // Search
    'showing_results' => 'عرض :count من :total نتيجة لـ ":query"',
    'no_results' => 'لا توجد نتائج',
    'no_results_description' => 'لم نتمكن من العثور على أي مقالات تطابق ":query". جرب كلمات مختلفة.',

    // Contextual Help
    'help_articles' => 'مقالات المساعدة',
    'no_articles' => 'لا توجد مقالات',
    'no_articles_description' => 'لا توجد مقالات مساعدة لهذه الشاشة حتى الآن.',

    // Screen Guide
    'step_of' => 'الخطوة :current من :total',
    'skip_guide' => 'تخطي الدليل',
    'previous' => 'السابق',
    'next' => 'التالي',
    'finish' => 'إنهاء',

    // Screens (for ScreenMappingService)
    'screens' => [
        'dashboard' => 'لوحة التحكم',
        'patients.index' => 'قائمة المرضى',
        'patients.create' => 'إضافة مريض',
        'patients.edit' => 'تعديل مريض',
        'patients.view' => 'تفاصيل المريض',
        'appointments.index' => 'قائمة المواعيد',
        'appointments.create' => 'إضافة موعد',
        'appointments.calendar' => 'التقويم',
        'services.index' => 'الخدمات',
        'invoices.index' => 'الفواتير',
        'invoices.create' => 'إنشاء فاتورة',
        'inventory.products' => 'المنتجات',
        'staff.index' => 'الموظفين',
        'users.index' => 'المستخدمين',
        'settings.branches' => 'الفروع',
        'accounting.journal' => 'قيود اليومية',
        'reports.index' => 'التقارير',
    ],
];
