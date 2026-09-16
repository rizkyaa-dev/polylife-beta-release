<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-flash'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
    ],

    'ai_provider' => env('AI_PROVIDER', 'gemini'),
    'ai_queue_connection' => env('AI_QUEUE_CONNECTION'),
    'ai_fast_queue' => env('AI_FAST_QUEUE', 'ai-fast'),
    'ai_heavy_queue' => env('AI_HEAVY_QUEUE', 'ai-heavy'),
    'ai_redispatch_after_seconds' => (int) env('AI_REDISPATCH_AFTER_SECONDS', 120),
    'ai_worker_start_timeout_seconds' => (int) env('AI_WORKER_START_TIMEOUT_SECONDS', 300),
    'ai_rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 8),
    'ai_max_active_runs_per_user' => (int) env('AI_MAX_ACTIVE_RUNS_PER_USER', 2),
    'ai_max_active_runs_global' => (int) env('AI_MAX_ACTIVE_RUNS_GLOBAL', 100),
    'ai_provider_max_concurrency' => (int) env('AI_PROVIDER_MAX_CONCURRENCY', 20),
    'ai_provider_retries' => (int) env('AI_PROVIDER_RETRIES', 1),
    'ai_circuit_failure_threshold' => (int) env('AI_CIRCUIT_FAILURE_THRESHOLD', 5),
    'ai_circuit_cooldown_seconds' => (int) env('AI_CIRCUIT_COOLDOWN_SECONDS', 30),
    'ai_provider_success_log_sample' => (float) env('AI_PROVIDER_SUCCESS_LOG_SAMPLE', 0.05),
    'ai_sensitive_data_retention_days' => (int) env('AI_SENSITIVE_DATA_RETENTION_DAYS', 30),
    'ai_context_token_budget' => (int) env('AI_CONTEXT_TOKEN_BUDGET', 24000),
    'ai_max_tool_calls_per_response' => (int) env('AI_MAX_TOOL_CALLS_PER_RESPONSE', 8),
    'ai_max_tool_calls_per_run' => (int) env('AI_MAX_TOOL_CALLS_PER_RUN', 16),
    'ai_coding_output_tokens' => (int) env('AI_CODING_OUTPUT_TOKENS', 16384),

];
