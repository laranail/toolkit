<?php

declare(strict_types=1);

return [
    'enable_https_support' => env('ENABLE_HTTPS_SUPPORT', false),
    'cache_site_map' => env('ENABLE_CACHE_SITE_MAP', false),
    'locale' => env('APP_LOCALE', 'en'),

    'app_url' => env('APP_URL'),

    'blade-javascript' => [

    ],

    'package' => [
        'publishing_tag_id' => env('LARANAIL_PACKAGE_PUBLISHING_TAG_ID', 'lrn'),
    ],

    'using_uuids_for_id' => env('LARANAIL_USING_UUIDS_FOR_ID', false),
    'using_ulids_for_id' => env('LARANAIL_USING_ULIDS_FOR_ID', false),
    'type_id' => env('LARANAIL_USING_TYPE_ID', 'BIGINT'),
];
