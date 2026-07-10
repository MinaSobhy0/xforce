<?php

return [

    // Redis-throttle bucket for the send jobs.
    // 60 = 1 email/sec (conservative — matches "one by one to avoid scam flags").
    // Raise as the sending domain warms up.
    'throttle_per_minute' => (int) env('PLATFORM_EMAIL_THROTTLE_PER_MINUTE', 60),

    // Weekly per-address send limit enforced in Phase 6 guardrails.
    // Anything above this in a rolling 7-day window is skipped.
    'weekly_per_address_limit' => (int) env('PLATFORM_EMAIL_WEEKLY_LIMIT', 2),

    // Feature master switch. When false, campaign Send Now buttons are
    // hidden — useful during warm-up / migration windows.
    'enabled' => (bool) env('PLATFORM_EMAIL_ENABLED', true),

];
