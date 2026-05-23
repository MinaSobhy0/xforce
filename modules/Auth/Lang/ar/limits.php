<?php

return [
    'email' => [
        'subject' => 'إجراء مطلوب: تم تجاوز حد المستخدمين',
        'greeting' => 'مرحباً :name،',
        'exceeded_message' => 'مؤسستك لديها حالياً :current مستخدم، وهذا يتجاوز حد خطتك البالغ :limit مستخدم بمقدار :overage مستخدم(ين).',
        'grace_period' => 'لديك :days يوم (حتى :date) لشراء مقاعد مستخدمين إضافية لتجنب انقطاع الخدمة.',
        'action' => 'ترقية خطتك',
        'contact_support' => 'إذا كانت لديك أي أسئلة، يرجى التواصل مع فريق الدعم لدينا.',
    ],

    'notification' => [
        'title' => 'تم تجاوز حد المستخدمين',
        'body' => 'لديك :current مستخدم ولكن الحد الأقصى هو :limit. يرجى شراء مستخدمين إضافيين.',
    ],

    'banner' => [
        'warning' => 'لقد تجاوزت حد المستخدمين (:current/:limit). يرجى شراء مستخدمين إضافيين خلال :days يوم.',
        'expired' => 'انتهت فترة السماح! لديك :current مستخدم ولكن الحد الأقصى هو :limit. يرجى الترقية فوراً.',
        'action' => 'الترقية الآن',

        // Subscription banners (past_due / grace / expired)
        'subscription_overdue' => 'دفع الاشتراك متأخر (منذ :days_ago يوم). سيتم إيقاف الحساب تلقائيًا خلال :days_until_suspend يوم.',
        'subscription_grace'   => 'الاشتراك في فترة سماح. يرجى التجديد قبل :date لتجنب انقطاع الخدمة.',
        'contact_support'      => 'تواصل مع الدعم',
    ],
];
