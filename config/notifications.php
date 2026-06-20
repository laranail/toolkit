<?php

declare(strict_types=1);

return [
    'queueable' => env('NOTIFICATIONS_QUEUEABLE', false),
    'queue_connection' => env('NOTIFICATIONS_QUEUE_CONNECTION', null),
    'queue_name' => env('NOTIFICATIONS_QUEUE_NAME', 'notifications'),

    'channels' => [
        'log' => [
            'enabled' => true,
            'default' => true,
        ],

        'email' => [
            'enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', false),
            'from' => env('MAIL_FROM_ADDRESS'),
            'to' => env('NOTIFICATIONS_EMAIL_TO'),
            'default_subject' => 'System Notification',
        ],

        'slack' => [
            'enabled' => env('NOTIFICATIONS_SLACK_ENABLED', false),
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
            'username' => 'Notification Bot',
            'icon' => ':robot_face:',
        ],

        'discord' => [
            'enabled' => env('NOTIFICATIONS_DISCORD_ENABLED', false),
            'webhook_url' => env('DISCORD_WEBHOOK_URL'),
            'username' => 'Notification Bot',
        ],

        'sms' => [
            'enabled' => env('NOTIFICATIONS_SMS_ENABLED', false),
            'api_key' => env('SMS_API_KEY'),
            'api_url' => env('SMS_API_URL'),
            'from' => env('SMS_FROM_NUMBER'),
        ],

        'push' => [
            'enabled' => env('NOTIFICATIONS_PUSH_ENABLED', false),
            'api_key' => env('ONESIGNAL_API_KEY'),
            'app_id' => env('ONESIGNAL_APP_ID'),
        ],

        'database' => [
            'enabled' => true,
            'table' => 'notifications',
        ],

        'cache' => [
            'enabled' => true,
            'key_prefix' => 'notification_',
            'ttl' => 3600,
        ],

        'file' => [
            'enabled' => env('NOTIFICATIONS_FILE_ENABLED', false),
            'path' => storage_path('logs/notifications.log'),
            'rotation' => true,
            'max_size' => 10485760, // 10MB
        ],

        'webhook' => [
            'enabled' => env('NOTIFICATIONS_WEBHOOK_ENABLED', false),
            'url' => env('WEBHOOK_URL'),
            'method' => 'POST',
            'headers' => [],
        ],

        'apple_business_messages' => [
            'enabled' => env('NOTIFICATIONS_APPLE_ENABLED', false),
            'business_id' => env('APPLE_BUSINESS_ID'),
            'api_key' => env('APPLE_API_KEY'),
            'api_secret' => env('APPLE_API_SECRET'),
        ],
    ],
];
