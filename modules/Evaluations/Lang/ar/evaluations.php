<?php

return [
    'evaluations' => 'التقييمات',
    'evaluation' => 'تقييم',

    'fields' => [
        'date' => 'التاريخ',
        'patient' => 'المريض',
        'visit' => 'الزيارة',
        'branch' => 'الفرع',
        'overall_rating' => 'التقييم العام',
        'service_quality_rating' => 'جودة الخدمة',
        'staff_friendliness_rating' => 'ودية الموظفين',
        'cleanliness_rating' => 'النظافة',
        'wait_time_rating' => 'وقت الانتظار',
        'value_for_money_rating' => 'القيمة مقابل المال',
        'satisfaction_score' => 'درجة الرضا',
        'nps_category' => 'فئة NPS',
        'would_recommend' => 'يوصي بنا',
        'feedback_text' => 'الملاحظات',
        'improvement_suggestions' => 'اقتراحات للتحسين',
        'source' => 'المصدر',
        'evaluated_by' => 'تم التقييم بواسطة',
        'has_feedback' => 'يوجد ملاحظات',
        'nps' => 'NPS',
    ],

    'sections' => [
        'visit_info' => 'معلومات الزيارة',
        'ratings' => 'التقييمات',
        'nps' => 'مؤشر صافي الترويج',
        'feedback' => 'ملاحظات المريض',
    ],

    'tabs' => [
        'all' => 'الكل',
        'positive' => 'إيجابي',
        'neutral' => 'محايد',
        'negative' => 'سلبي',
        'with_feedback' => 'مع ملاحظات',
    ],

    'sources' => [
        'staff' => 'إدخال الموظف',
        'kiosk' => 'كشك العيادة',
        'sms' => 'استبيان SMS',
        'email' => 'استبيان البريد الإلكتروني',
        'portal' => 'بوابة المريض',
    ],

    'nps' => [
        'promoter' => 'مروج',
        'passive' => 'محايد',
        'detractor' => 'منتقد',
    ],

    'filters' => [
        'from' => 'من تاريخ',
        'until' => 'إلى تاريخ',
    ],

    'actions' => [
        'take_review' => 'أخذ تقييم',
        'view_evaluation' => 'عرض التقييم',
    ],

    'form' => [
        'overall_rating_helper' => 'كيف تقيم تجربتك العامة؟',
        'service_quality_helper' => 'جودة الخدمات المقدمة',
        'staff_friendliness_helper' => 'ما مدى ودية وتعاون موظفينا؟',
        'cleanliness_helper' => 'نظافة العيادة',
        'wait_time_helper' => 'هل كان وقت الانتظار مقبولاً؟',
        'value_for_money_helper' => 'هل حصلت على قيمة جيدة مقابل أموالك؟',
        'satisfaction_score_helper' => 'على مقياس من 0-10، ما مدى رضاك؟',
        'would_recommend_helper' => 'هل ستوصي بنا للأصدقاء والعائلة؟',
        'feedback_placeholder' => 'شاركنا تجربتك...',
        'suggestions_placeholder' => 'كيف يمكننا التحسين؟',
    ],

    'no_feedback' => 'لا توجد ملاحظات',
    'no_suggestions' => 'لا توجد اقتراحات',

    'report' => [
        'title' => 'تقرير الرضا',
        'average_rating' => 'متوسط التقييم',
        'total_evaluations' => 'إجمالي التقييمات',
        'response_rate' => 'معدل الاستجابة',
        'nps_score' => 'مؤشر NPS',
        'positive' => 'إيجابي',
        'visits' => 'زيارات',
        'rating_distribution' => 'توزيع التقييمات',
        'low_ratings_follow_up' => 'تقييمات منخفضة للمتابعة',
    ],

    'messages' => [
        'evaluation_created' => 'تم إرسال التقييم بنجاح!',
        'already_evaluated' => 'تم تقييم هذه الزيارة بالفعل.',
    ],
];
