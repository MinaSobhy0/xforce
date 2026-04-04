<?php

return [
    'name' => 'KnowledgeBase',

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    */
    'enable_contextual_help' => env('KNOWLEDGEBASE_CONTEXTUAL_HELP', true),
    'enable_interactive_guides' => env('KNOWLEDGEBASE_GUIDES', true),
    'enable_feedback' => env('KNOWLEDGEBASE_FEEDBACK', true),
    'enable_search_logging' => env('KNOWLEDGEBASE_SEARCH_LOGGING', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => env('KNOWLEDGEBASE_CACHE_TTL', 3600), // 1 hour
    'cache_prefix' => 'kb_',

    /*
    |--------------------------------------------------------------------------
    | Search Settings
    |--------------------------------------------------------------------------
    */
    'search_min_length' => 2,
    'search_results_limit' => 20,

    /*
    |--------------------------------------------------------------------------
    | Guide Settings
    |--------------------------------------------------------------------------
    */
    'guide_spotlight_opacity' => 0.7,
    'guide_animation_duration' => 300, // milliseconds
];
