<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default CAPTCHA Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default CAPTCHA provider that will be used
    | when no specific provider is requested.
    |
    | Supported: "recaptcha", "turnstile", "hcaptcha"
    |
    */

    'default_provider' => env('LARANAIL_CAPTCHA_DEFAULT_PROVIDER', 'recaptcha'),

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google reCAPTCHA v2 and v3.
    |
    */

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY', ''),
        'secret_key' => env('RECAPTCHA_SECRET_KEY', ''),
        'min_score' => env('RECAPTCHA_MIN_SCORE', 0.5),
        'timeout' => env('RECAPTCHA_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Cloudflare Turnstile CAPTCHA.
    |
    */

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
        'timeout' => env('TURNSTILE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | hCaptcha Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for hCaptcha service.
    |
    */

    'hcaptcha' => [
        'site_key' => env('HCAPTCHA_SITE_KEY', ''),
        'secret_key' => env('HCAPTCHA_SECRET_KEY', ''),
        'timeout' => env('HCAPTCHA_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA Behavior Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how CAPTCHA verification behaves in different
    | environments and scenarios.
    |
    */

    'behavior' => [
        // Allow requests to proceed if CAPTCHA is not configured (useful for development)
        'allow_unconfigured' => env('CAPTCHA_ALLOW_UNCONFIGURED', false),
        
        // Log failed verification attempts
        'log_failures' => env('CAPTCHA_LOG_FAILURES', true),
        
        // Cache verification results (in seconds, 0 to disable)
        'cache_duration' => env('CAPTCHA_CACHE_DURATION', 0),
        
        // Maximum number of verification attempts per IP per hour
        'max_attempts_per_hour' => env('CAPTCHA_MAX_ATTEMPTS_PER_HOUR', 100),
    ],






];
