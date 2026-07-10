<?php

declare(strict_types=1);

return [
    /*
     * Self-contained LLM module (Modules\LLM). One consistent prefix:
     * `laranail.toolkit.llm.*` — `default_provider` selects the driver bound to
     * LLMProviderInterface, and each provider's credentials/tuning live nested
     * under `llm.<provider>` (NOT as siblings of `llm`).
     */
    'llm' => [
        'default_provider' => env('LLM_DEFAULT_PROVIDER', 'openai'), // openai | gemini | claude

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
    ],

    'cache' => [
        'default_expiration' => 60,
        'default_tags' => [],
        // Optional key prefix applied to every read/write/forget.
        'namespace' => env('LARANAIL_CACHE_NAMESPACE', ''),
        // When true, LogCacheEvents logs the cache-maintenance lifecycle
        // (clearing/cleared/failed). Off by default to stay quiet.
        'log_events' => env('LARANAIL_CACHE_LOG_EVENTS', false),
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

    /*
     * Named Python (or any external HTTP) microservices consumed by
     * Services\PythonApiService. Each entry builds a configured client:
     * base_url + timeout + retry + JSON + SSL/CA-cert verification, with a
     * per-service health contract (path + expected key/value). `timeout` may be
     * null to inherit the shared `http.request_timeout` above. Point `ca_cert`
     * at a mkcert/Caddy CA bundle for HTTPS behind a local reverse proxy.
     */
    'python' => [
        'services' => [
            'fastapi' => [
                'base_url' => env('PYTHON_FASTAPI_URL', 'http://127.0.0.1:8000'),
                'timeout' => env('PYTHON_FASTAPI_TIMEOUT'),
                'verify_ssl' => env('PYTHON_FASTAPI_VERIFY_SSL', true),
                'ca_cert' => env('PYTHON_FASTAPI_CA_CERT'),
                'health_path' => env('PYTHON_FASTAPI_HEALTH_PATH', '/health'),
                'health_key' => env('PYTHON_FASTAPI_HEALTH_KEY', 'status'),
                'healthy_value' => env('PYTHON_FASTAPI_HEALTHY_VALUE', 'healthy'),
                'retry_times' => env('PYTHON_FASTAPI_RETRY_TIMES', 3),
                'retry_sleep_ms' => env('PYTHON_FASTAPI_RETRY_SLEEP_MS', 100),
            ],
            'flask' => [
                'base_url' => env('PYTHON_FLASK_URL', 'http://127.0.0.1:5000'),
                'timeout' => env('PYTHON_FLASK_TIMEOUT'),
                'verify_ssl' => env('PYTHON_FLASK_VERIFY_SSL', true),
                'ca_cert' => env('PYTHON_FLASK_CA_CERT'),
                'health_path' => env('PYTHON_FLASK_HEALTH_PATH', '/health'),
                'health_key' => env('PYTHON_FLASK_HEALTH_KEY', 'status'),
                'healthy_value' => env('PYTHON_FLASK_HEALTHY_VALUE', 'healthy'),
                'retry_times' => env('PYTHON_FLASK_RETRY_TIMES', 3),
                'retry_sleep_ms' => env('PYTHON_FLASK_RETRY_SLEEP_MS', 100),
            ],
        ],
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

    /*
     * Runtime settings store (Services\SettingsStore) — a JSON file of dynamic,
     * persisted-at-runtime values, kept separate from this static config.
     */
    'settings' => [
        'disk' => env('LARANAIL_SETTINGS_DISK', 'local'),
        'path' => env('LARANAIL_SETTINGS_PATH', 'laranail/settings.json'),
    ],
];
