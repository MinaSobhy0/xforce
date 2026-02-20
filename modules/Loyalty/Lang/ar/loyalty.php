<?php

return [
    // Module info
    'module_name' => 'برنامج الولاء',
    'module_description' => 'نظام نقاط الولاء مع المكافآت والإحالات',

    // Navigation
    'navigation_label' => 'برنامج الولاء',
    'navigation_group' => 'المبيعات',

    // Resources
    'loyalty_rules' => 'قواعد الولاء',
    'loyalty_rule' => 'قاعدة ولاء',
    'loyalty_transactions' => 'معاملات النقاط',
    'loyalty_transaction' => 'معاملة نقاط',
    'referral_programs' => 'برامج الإحالة',
    'referral_program' => 'برنامج إحالة',
    'referrals' => 'الإحالات',
    'referral' => 'إحالة',

    // Fields
    'fields' => [
        'name' => 'الاسم',
        'description' => 'الوصف',
        'type' => 'النوع',
        'points_amount' => 'عدد النقاط',
        'points_per_currency_unit' => 'نقاط لكل جنيه',
        'min_spend' => 'الحد الأدنى للإنفاق',
        'max_points' => 'الحد الأقصى للنقاط',
        'treatment' => 'العلاج',
        'treatment_category' => 'فئة العلاج',
        'multiplier' => 'المضاعف',
        'conditions' => 'الشروط',
        'is_active' => 'نشط',
        'priority' => 'الأولوية',
        'starts_at' => 'يبدأ في',
        'ends_at' => 'ينتهي في',
        'patient' => 'المريض',
        'points' => 'النقاط',
        'running_balance' => 'الرصيد الجاري',
        'reference' => 'المرجع',
        'expires_at' => 'ينتهي في',
        'created_by' => 'أنشأ بواسطة',
        'code' => 'الكود',
        'referrer' => 'المُحيل',
        'referred' => 'المُحال',
        'status' => 'الحالة',
        'referrer_points' => 'نقاط المُحيل',
        'referred_points' => 'نقاط المُحال',
        'referrer_discount' => 'خصم المُحيل %',
        'referred_discount' => 'خصم المُحال %',
        'require_first_purchase' => 'يتطلب أول شراء',
        'loyalty_points' => 'نقاط الولاء',
        'lifetime_points' => 'النقاط مدى الحياة',
        'tier' => 'المستوى',
    ],

    // Sections
    'sections' => [
        'basic_info' => 'المعلومات الأساسية',
        'points_config' => 'إعدادات النقاط',
        'targeting' => 'الاستهداف',
        'validity' => 'فترة الصلاحية',
        'rewards' => 'المكافآت',
        'requirements' => 'المتطلبات',
        'transaction_details' => 'تفاصيل المعاملة',
    ],

    // Rule types
    'rule_types' => [
        'per_spend' => 'لكل إنفاق',
        'per_visit' => 'لكل زيارة',
        'referral' => 'إحالة',
        'birthday' => 'عيد ميلاد',
        'signup' => 'تسجيل',
        'first_purchase' => 'أول شراء',
    ],

    // Transaction types
    'transaction_types' => [
        'earn' => 'مكتسب',
        'redeem' => 'مستبدل',
        'expire' => 'منتهي',
        'adjust' => 'معدل',
        'refund' => 'مسترد',
        'bonus' => 'مكافأة',
        'referral' => 'إحالة',
    ],

    // Referral statuses
    'referral_statuses' => [
        'pending' => 'معلق',
        'completed' => 'مكتمل',
        'rewarded' => 'تم المكافأة',
        'expired' => 'منتهي',
        'cancelled' => 'ملغي',
    ],

    // Tiers
    'tiers' => [
        'bronze' => 'برونزي',
        'silver' => 'فضي',
        'gold' => 'ذهبي',
        'platinum' => 'بلاتيني',
        'diamond' => 'ماسي',
    ],

    // Actions
    'actions' => [
        'earn_points' => 'اكتساب نقاط',
        'redeem_points' => 'استبدال نقاط',
        'adjust_points' => 'تعديل النقاط',
        'create_referral' => 'إنشاء كود إحالة',
        'process_referral' => 'معالجة الإحالة',
        'view_history' => 'عرض السجل',
    ],

    // Transaction descriptions
    'transaction_descriptions' => [
        'payment_bonus' => 'نقاط مكتسبة من دفعة :amount جنيه',
        'visit_bonus' => 'نقاط مكتسبة للزيارة المكتملة',
        'birthday_bonus' => 'نقاط مكافأة عيد الميلاد',
        'first_purchase_bonus' => 'مكافأة ترحيبية لأول شراء',
        'referral_bonus_referrer' => 'مكافأة الإحالة - أحلت صديقاً',
        'referral_bonus_referred' => 'مكافأة ترحيبية - تمت إحالتك من صديق',
        'redeemed' => 'نقاط مستبدلة',
        'points_expired' => 'نقاط منتهية بسبب عدم النشاط',
        'manual_adjustment' => 'تعديل يدوي من المسؤول',
    ],

    // Messages
    'messages' => [
        'points_earned' => 'تم اكتساب :points نقطة بنجاح',
        'points_redeemed' => 'تم استبدال :points نقطة بنجاح',
        'points_adjusted' => 'تم تعديل النقاط بنجاح',
        'referral_created' => 'تم إنشاء كود الإحالة: :code',
        'referral_completed' => 'اكتملت الإحالة وتم منح المكافآت',
        'insufficient_points' => 'نقاط غير كافية. لديك :available نقطة لكنك تحتاج :required.',
    ],

    // Errors
    'errors' => [
        'insufficient_points' => 'نقاط غير كافية. المطلوب: :requested، المتاح: :available',
        'invalid_referral' => 'كود إحالة غير صالح أو منتهي',
        'self_referral' => 'لا يمكنك إحالة نفسك',
        'already_referred' => 'تمت إحالة هذا المريض بالفعل',
        'max_referrals_reached' => 'تم الوصول للحد الأقصى من الإحالات',
    ],

    // Stats
    'stats' => [
        'total_points_earned' => 'إجمالي النقاط المكتسبة',
        'total_points_redeemed' => 'إجمالي النقاط المستبدلة',
        'active_members' => 'الأعضاء النشطون',
        'referrals_this_month' => 'الإحالات هذا الشهر',
        'points_expiring_soon' => 'نقاط تنتهي قريباً',
    ],

    // Widgets
    'widgets' => [
        'points_summary' => 'ملخص النقاط',
        'recent_transactions' => 'آخر المعاملات',
        'top_earners' => 'أعلى مكتسبي النقاط',
        'referral_leaderboard' => 'قائمة متصدري الإحالات',
    ],

    // Notifications
    'notifications' => [
        'points_earned_title' => 'تم اكتساب نقاط!',
        'points_earned_body' => 'اكتسبت :points نقطة ولاء. رصيدك الآن :balance نقطة.',
        'tier_upgrade_title' => 'تهانينا! ترقية المستوى',
        'tier_upgrade_body' => 'تمت ترقيتك إلى المستوى :tier! استمتع بمزاياك الجديدة.',
        'points_expiring_title' => 'نقاط تنتهي قريباً',
        'points_expiring_body' => 'لديك :points نقطة تنتهي في :date. استخدمها قبل انتهائها!',
    ],
];
