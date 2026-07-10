<?php

/*
|--------------------------------------------------------------------------
| LLM Provider Registry
|--------------------------------------------------------------------------
|
| Every model the platform can use for AI features (email personalization,
| CSV import organization, future workflows) is registered here. Each
| entry maps a stable model key (e.g. 'deepseek-v3') to a driver + API
| key + pricing.
|
| Pricing units: input_cost_per_mtok / output_cost_per_mtok are USD
| per million tokens. Update as providers change their prices.
|
| Adding a new model:
|   1. Register it below.
|   2. Add its API key to .env.
|   3. It automatically appears in the Filament model dropdowns
|      (Arr::where(...) filters out entries without a configured key).
|
| Add a whole new PROVIDER (not just a new model):
|   1. Implement App\Services\Ai\Contracts\LlmProvider.
|   2. Add the driver class name below.
|
*/

return [

    'default' => env('LLM_DEFAULT', 'deepseek-v3'),

    // Fallback chain when the primary throws (rate-limit / 5xx / timeout).
    // Empty array = no fallback (fail hard). Otherwise, tried in order.
    'fallbacks' => [
        // 'deepseek-v3',
    ],

    'providers' => [

        'deepseek-v3' => [
            'driver' => 'deepseek',
            'model' => 'deepseek-chat',
            'api_key' => env('DEEPSEEK_API_KEY'),
            'endpoint' => 'https://api.deepseek.com/chat/completions',
            'input_cost_per_mtok' => 0.14,
            'output_cost_per_mtok' => 0.28,
            'context_window' => 64_000,
            'label' => 'DeepSeek V3 (cheapest)',
        ],

        // Gemini flash-lite has a free tier; the full flash variants
        // are billing-only. Using the "-lite-latest" alias so the config
        // stays evergreen as Google promotes newer lite generations.
        'gemini-flash' => [
            'driver' => 'gemini',
            'model' => 'gemini-flash-lite-latest',
            'api_key' => env('GEMINI_API_KEY'),
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'input_cost_per_mtok' => 0.075,
            'output_cost_per_mtok' => 0.30,
            'context_window' => 1_000_000,
            'label' => 'Gemini Flash Lite (free tier)',
        ],

        'claude-haiku-4-5' => [
            'driver' => 'claude',
            'model' => 'claude-haiku-4-5-20251001',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'input_cost_per_mtok' => 1.00,
            'output_cost_per_mtok' => 5.00,
            'context_window' => 200_000,
            'label' => 'Claude Haiku 4.5',
        ],

        'claude-sonnet-5' => [
            'driver' => 'claude',
            'model' => 'claude-sonnet-5',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'input_cost_per_mtok' => 3.00,
            'output_cost_per_mtok' => 15.00,
            'context_window' => 200_000,
            'label' => 'Claude Sonnet 5 (best quality)',
        ],

        'grok-mini' => [
            'driver' => 'grok',
            'model' => 'grok-2-mini',
            'api_key' => env('XAI_API_KEY'),
            'endpoint' => 'https://api.x.ai/v1/chat/completions',
            'input_cost_per_mtok' => 0.30,
            'output_cost_per_mtok' => 0.50,
            'context_window' => 131_072,
            'label' => 'Grok 2 Mini',
        ],

        'gpt-4o-mini' => [
            'driver' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => env('OPENAI_API_KEY'),
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'input_cost_per_mtok' => 0.15,
            'output_cost_per_mtok' => 0.60,
            'context_window' => 128_000,
            'label' => 'OpenAI GPT-4o mini',
        ],

    ],

    'drivers' => [
        'deepseek' => \App\Services\Ai\Drivers\DeepSeekProvider::class,
        'gemini' => \App\Services\Ai\Drivers\GeminiProvider::class,
        'claude' => \App\Services\Ai\Drivers\ClaudeProvider::class,
        'grok' => \App\Services\Ai\Drivers\GrokProvider::class,
        'openai' => \App\Services\Ai\Drivers\OpenAiProvider::class,
    ],

    // Per-request defaults. Individual LlmRequest instances can override.
    'defaults' => [
        'temperature' => 0.4,
        'max_tokens' => 1024,
        'timeout' => 60, // seconds
    ],

];
