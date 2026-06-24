<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Select-Box Label
    |--------------------------------------------------------------------------
    |
    | The country name key used by `Atlas::forSelectBox()` when no explicit
    | label is given. Supported: "name", "official_name", "native_name".
    |
    */

    'default_label' => env('LARANAIL_ATLAS_DEFAULT_LABEL', 'name'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) the derived country / currency / timezone lists are
    | cached. The underlying data package ships static JSON, so a long TTL is
    | safe. Set to 0 to recompute on every call.
    |
    */

    'cache_ttl' => env('LARANAIL_ATLAS_CACHE_TTL', 1440),
];
