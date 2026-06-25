<?php

declare(strict_types=1);

return [
    'llm' => [
        'default_provider' => env('LLM_DEFAULT_PROVIDER', 'openai'), // openai | gemini | claude
    ],

    'cache' => [
        'default_expiration' => 60,
        'default_tags' => [],
        // Optional key prefix applied to get/forget/remember/put/many/inc/dec.
        'namespace' => env('LARANAIL_CACHE_NAMESPACE', ''),
    ],

    /*
     * Guzzle / HTTP client defaults consumed by Services\HttpConfigurationService.
     * Each value is overridable via its env key; the service reads these under
     * `laranail.toolkit.http.*` (merged from this file).
     */
    'http' => [
        'persist_connection' => env('GUZZLE_PERSIST_CONNECTION', true),
        'request_timeout' => env('GUZZLE_REQUEST_TIMEOUT', 60),
        'max_retries' => env('GUZZLE_MAX_RETRIES', 10),
        'cache_ttl' => env('GUZZLE_CACHE_TTL', 10),
    ],

    'access_log' => [
        // Toggle persistence of the access.log middleware.
        'enabled' => env('LARANAIL_ACCESS_LOG_ENABLED', true),

        // Request keys whose values are redacted before being stored.
        // null = use the middleware's built-in default deny-list.
        'redact' => null,
    ],

    'rate_limiting' => [
        'default_max_attempts' => 60,
        'default_decay_minutes' => 1,
        'cache_prefix' => 'rate_limit:',
        'defaults' => [
            'api' => [
                'max_attempts' => 60,
                'decay_minutes' => 1,
            ],
            'auth' => [
                'max_attempts' => 5,
                'decay_minutes' => 15,
            ],
            'download' => [
                'max_attempts' => 3,
                'decay_minutes' => 1,
            ],
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'max_retries' => env('OPENAI_MAX_RETRIES', 3),
        'retry_delay' => env('OPENAI_RETRY_DELAY', 2),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-3.5-turbo'),
        'default_temperature' => env('OPENAI_DEFAULT_TEMPERATURE', 0.7),
        'default_max_tokens' => env('OPENAI_DEFAULT_MAX_TOKENS', 300),
        'default_top_p' => env('OPENAI_DEFAULT_TOP_P', 1.0),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'max_retries' => env('GEMINI_MAX_RETRIES', 3),
        'retry_delay' => env('GEMINI_RETRY_DELAY', 2),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.0-flash'),
        'default_temperature' => env('GEMINI_DEFAULT_TEMPERATURE', 0.7),
        'default_max_tokens' => env('GEMINI_DEFAULT_MAX_TOKENS', 300),
        'default_top_p' => env('GEMINI_DEFAULT_TOP_P', 1.0),
    ],

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'max_retries' => env('CLAUDE_MAX_RETRIES', 3),
        'retry_delay' => env('CLAUDE_RETRY_DELAY', 2),
        'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com'),
        'default_model' => env('CLAUDE_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
        'default_temperature' => env('CLAUDE_DEFAULT_TEMPERATURE', 1.0),
        'default_max_tokens' => env('CLAUDE_DEFAULT_MAX_TOKENS', 1024),
        'default_top_p' => env('CLAUDE_DEFAULT_TOP_P', 1.0),
    ],

    /*
     * Runtime settings store (Services\SettingsStore) — a JSON file of dynamic,
     * persisted-at-runtime values, kept separate from this static config.
     */
    'settings' => [
        'disk' => env('LARANAIL_SETTINGS_DISK', 'local'),
        'path' => env('LARANAIL_SETTINGS_PATH', 'laranail/settings.json'),
    ],
];
