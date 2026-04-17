<?php

return [
    'name' => 'FaceChart',

    // 3D Model settings
    'model_path' => '/models/face_head.glb',
    'model_scale' => 1.0,

    // 2D Face Image settings
    'face_2d_image_path' => env('FACE_CHART_2D_IMAGE', '/images/face_2d_front.png'),
    'face_2d_images' => [
        'front' => '/images/face_2d_front.png',
        'left' => '/images/face_2d_left.png',
        'right' => '/images/face_2d_right.png',
    ],
    'face_2d_canvas_max_width' => 1800,
    'face_2d_canvas_max_height' => 2200,

    // Default marker settings
    'default_marker_color' => '#FF6B6B',
    'default_marker_size' => 1.0,
    'historical_marker_opacity' => 0.6,

    // Marker colors by type
    'marker_colors' => [
        'injection' => '#3B82F6',      // Blue - Botox
        'filler_point' => '#EF4444',   // Red - Filler
        'laser_spot' => '#F59E0B',     // Amber - Laser
        'thread_anchor' => '#8B5CF6', // Purple - Thread
        'marking' => '#10B981',        // Green - General marking
    ],

    // Face regions for categorization
    'face_regions' => [
        'forehead' => ['en' => 'Forehead', 'ar' => 'الجبهة'],
        'glabella' => ['en' => 'Glabella', 'ar' => 'الحاجبين'],
        'temples' => ['en' => 'Temples', 'ar' => 'الصدغين'],
        'crow_feet' => ['en' => "Crow's Feet", 'ar' => 'أقدام الغراب'],
        'upper_eyelid' => ['en' => 'Upper Eyelid', 'ar' => 'الجفن العلوي'],
        'lower_eyelid' => ['en' => 'Lower Eyelid', 'ar' => 'الجفن السفلي'],
        'nose' => ['en' => 'Nose', 'ar' => 'الأنف'],
        'cheeks' => ['en' => 'Cheeks', 'ar' => 'الخدين'],
        'nasolabial' => ['en' => 'Nasolabial Folds', 'ar' => 'الطيات الأنفية الشفوية'],
        'upper_lip' => ['en' => 'Upper Lip', 'ar' => 'الشفة العليا'],
        'lower_lip' => ['en' => 'Lower Lip', 'ar' => 'الشفة السفلى'],
        'marionette' => ['en' => 'Marionette Lines', 'ar' => 'خطوط الماريونيت'],
        'chin' => ['en' => 'Chin', 'ar' => 'الذقن'],
        'jawline' => ['en' => 'Jawline', 'ar' => 'خط الفك'],
        'neck' => ['en' => 'Neck', 'ar' => 'الرقبة'],
    ],

    // Marker types
    'marker_types' => [
        'injection' => ['en' => 'Injection', 'ar' => 'حقن'],
        'filler_point' => ['en' => 'Filler Point', 'ar' => 'نقطة الفيلر'],
        'laser_spot' => ['en' => 'Laser Spot', 'ar' => 'نقطة الليزر'],
        'thread_anchor' => ['en' => 'Thread Anchor', 'ar' => 'مرساة الخيط'],
        'marking' => ['en' => 'Marking', 'ar' => 'علامة'],
    ],

    // Unit types
    'unit_types' => [
        'units' => ['en' => 'Units', 'ar' => 'وحدات'],
        'ml' => ['en' => 'mL', 'ar' => 'مل'],
        'cc' => ['en' => 'cc', 'ar' => 'سم مكعب'],
    ],

    // Browser caching for 3D model (1 year)
    'model_cache_max_age' => 31536000,
];
