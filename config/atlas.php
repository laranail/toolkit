<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Atlas configuration
|--------------------------------------------------------------------------
|
| The single, self-contained config file for the Atlas module. It carries
| the behavioural knobs (select-box label, cache TTL), the canonical
| continent display-name map, and the Laravel-locale language registry.
|
| Countries / currencies / continents / regions are DERIVED from the
| rinvex/countries data package at runtime — they are not configured here.
| Only the language registry is config-shaped, because the data package is
| country-centric (not locale-centric).
|
*/

$language = static fn (string $iso639_1, string $locale, string $native, string $dir, string $flag): array => [
    'iso639_1' => $iso639_1,
    'locale' => $locale,
    'native_name' => $native,
    'dir' => $dir,
    'flag' => $flag,
];

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
    | How long (in minutes) the derived country / currency / timezone /
    | continent lists are cached. The underlying data package ships static
    | JSON, so a long TTL is safe. Set to 0 to recompute on every call.
    |
    */

    'cache_ttl' => env('LARANAIL_ATLAS_CACHE_TTL', 1440),

    /*
    |--------------------------------------------------------------------------
    | Continent Display Names
    |--------------------------------------------------------------------------
    |
    | Canonical continent code => English name map. rinvex already carries a
    | clean `geo.continent` name per country, but a fixed seven-entry map is
    | kept here so `Atlas::continents()` is deterministic, ordered, and
    | overridable without touching the data package. Codes follow the
    | two-letter continent convention used by rinvex (AF, AN, AS, EU, NA,
    | OC, SA).
    |
    */

    'continents' => [
        'AF' => 'Africa',
        'AN' => 'Antarctica',
        'AS' => 'Asia',
        'EU' => 'Europe',
        'NA' => 'North America',
        'OC' => 'Oceania',
        'SA' => 'South America',
    ],

    /*
    |--------------------------------------------------------------------------
    | Language / Locale Registry
    |--------------------------------------------------------------------------
    |
    | Ported (de-bloated) from the legacy Atlas\Languages::$languages map. The
    | data package (rinvex/countries) is country-centric, so this
    | Laravel-locale shaped table is kept here as a resource: each entry is
    | keyed by its Laravel locale and carries the slim tuple needed to render
    | a locale switcher.
    |
    | Each value:
    |   'iso639_1'    => ISO 639-1 language code
    |   'locale'      => Laravel locale (mirrors the key)
    |   'native_name' => endonym (the language's own name)
    |   'dir'         => text direction ('ltr' | 'rtl')
    |   'flag'        => flag code (region/country code or a special label)
    |
    | @return array<string, array{iso639_1: string, locale: string, native_name: string, dir: string, flag: string}>
    */

    'languages' => [
        'af' => $language('af', 'af', 'Afrikaans', 'ltr', 'za'),
        'ar' => $language('ar', 'ar', 'العربية', 'rtl', 'ar'),
        'ary' => $language('ar', 'ary', 'العربية المغربية', 'rtl', 'ma'),
        'az' => $language('az', 'az', 'Azərbaycan', 'ltr', 'az'),
        'azb' => $language('az', 'azb', 'گؤنئی آذربایجان', 'rtl', 'az'),
        'bel' => $language('be', 'bel', 'Беларуская мова', 'ltr', 'by'),
        'bg_BG' => $language('bg', 'bg_BG', 'български', 'ltr', 'bg'),
        'bn_BD' => $language('bn', 'bn_BD', 'বাংলা', 'ltr', 'bd'),
        'bo' => $language('bo', 'bo', 'བོད་སྐད', 'ltr', 'bo'),
        'bs_BA' => $language('bs', 'bs_BA', 'Bosanski', 'ltr', 'ba'),
        'ceb' => $language('ceb', 'ceb', 'Cebuano', 'ltr', 'ph'),
        'cs_CZ' => $language('cs', 'cs_CZ', 'Čeština', 'ltr', 'cz'),
        'cy' => $language('cy', 'cy', 'Cymraeg', 'ltr', 'gb-wls'),
        'da_DK' => $language('da', 'da_DK', 'Dansk', 'ltr', 'dk'),
        'de_CH' => $language('de', 'de_CH', 'Deutsch', 'ltr', 'ch'),
        'de_DE' => $language('de', 'de_DE', 'Deutsch', 'ltr', 'de'),
        'el' => $language('el', 'el', 'Ελληνικά', 'ltr', 'gr'),
        'en_AU' => $language('en', 'en_AU', 'English', 'ltr', 'au'),
        'en_CA' => $language('en', 'en_CA', 'English', 'ltr', 'ca'),
        'en_GB' => $language('en', 'en_GB', 'English', 'ltr', 'gb'),
        'en_NZ' => $language('en', 'en_NZ', 'English', 'ltr', 'nz'),
        'en_US' => $language('en', 'en_US', 'English', 'ltr', 'us'),
        'en_ZA' => $language('en', 'en_ZA', 'English', 'ltr', 'za'),
        'es_AR' => $language('es', 'es_AR', 'Español', 'ltr', 'ar'),
        'es_CL' => $language('es', 'es_CL', 'Español', 'ltr', 'cl'),
        'es_CO' => $language('es', 'es_CO', 'Español', 'ltr', 'co'),
        'es_ES' => $language('es', 'es_ES', 'Español', 'ltr', 'es'),
        'es_MX' => $language('es', 'es_MX', 'Español', 'ltr', 'mx'),
        'es_PE' => $language('es', 'es_PE', 'Español', 'ltr', 'pe'),
        'es_VE' => $language('es', 'es_VE', 'Español', 'ltr', 've'),
        'et' => $language('et', 'et', 'Eesti', 'ltr', 'ee'),
        'eu' => $language('eu', 'eu', 'Euskara', 'ltr', 'es'),
        'fa_IR' => $language('fa', 'fa_IR', 'فارسی', 'rtl', 'ir'),
        'fi' => $language('fi', 'fi', 'Suomi', 'ltr', 'fi'),
        'fr_BE' => $language('fr', 'fr_BE', 'Français', 'ltr', 'be'),
        'fr_FR' => $language('fr', 'fr_FR', 'Français', 'ltr', 'fr'),
        'gd' => $language('gd', 'gd', 'Gàidhlig', 'ltr', 'gb-sct'),
        'gl_ES' => $language('gl', 'gl_ES', 'Galego', 'ltr', 'es'),
        'gu' => $language('gu', 'gu', 'ગુજરાતી', 'ltr', 'in'),
        'he_IL' => $language('he', 'he_IL', 'עברית', 'rtl', 'il'),
        'hi_IN' => $language('hi', 'hi_IN', 'हिन्दी', 'ltr', 'in'),
        'hr' => $language('hr', 'hr', 'Hrvatski', 'ltr', 'hr'),
        'hu_HU' => $language('hu', 'hu_HU', 'Magyar', 'ltr', 'hu'),
        'hy' => $language('hy', 'hy', 'Հայերեն', 'ltr', 'am'),
        'id_ID' => $language('id', 'id_ID', 'Bahasa Indonesia', 'ltr', 'id'),
        'is_IS' => $language('is', 'is_IS', 'Íslenska', 'ltr', 'is'),
        'it_IT' => $language('it', 'it_IT', 'Italiano', 'ltr', 'it'),
        'ja' => $language('ja', 'ja', '日本語', 'ltr', 'jp'),
        'ka_GE' => $language('ka', 'ka_GE', 'ქართული', 'ltr', 'ge'),
        'kk' => $language('kk', 'kk', 'Қазақ тілі', 'ltr', 'kz'),
        'km' => $language('km', 'km', 'ភាសាខ្មែរ', 'ltr', 'kh'),
        'ko_KR' => $language('ko', 'ko_KR', '한국어', 'ltr', 'kr'),
        'lo' => $language('lo', 'lo', 'ພາສາລາວ', 'ltr', 'la'),
        'lt_LT' => $language('lt', 'lt_LT', 'Lietuviškai', 'ltr', 'lt'),
        'lv' => $language('lv', 'lv', 'Latviešu valoda', 'ltr', 'lv'),
        'mk_MK' => $language('mk', 'mk_MK', 'македонски јазик', 'ltr', 'mk'),
        'mn' => $language('mn', 'mn', 'Монгол хэл', 'ltr', 'mn'),
        'mr' => $language('mr', 'mr', 'मराठी', 'ltr', 'in'),
        'ms_MY' => $language('ms', 'ms_MY', 'Bahasa Melayu', 'ltr', 'my'),
        'my_MM' => $language('my', 'my_MM', 'ဗမာစာ', 'ltr', 'mm'),
        'nb_NO' => $language('nb', 'nb_NO', 'Norsk Bokmål', 'ltr', 'no'),
        'ne_NP' => $language('ne', 'ne_NP', 'नेपाली', 'ltr', 'np'),
        'nl_NL' => $language('nl', 'nl_NL', 'Nederlands', 'ltr', 'nl'),
        'nn_NO' => $language('nn', 'nn_NO', 'Norsk Nynorsk', 'ltr', 'no'),
        'pl_PL' => $language('pl', 'pl_PL', 'Polski', 'ltr', 'pl'),
        'ps' => $language('ps', 'ps', 'پښتو', 'rtl', 'af'),
        'pt_BR' => $language('pt', 'pt_BR', 'Português', 'ltr', 'br'),
        'pt_PT' => $language('pt', 'pt_PT', 'Português', 'ltr', 'pt'),
        'ro_RO' => $language('ro', 'ro_RO', 'Română', 'ltr', 'ro'),
        'ru_RU' => $language('ru', 'ru_RU', 'Русский', 'ltr', 'ru'),
        'si_LK' => $language('si', 'si_LK', 'සිංහල', 'ltr', 'lk'),
        'sk_SK' => $language('sk', 'sk_SK', 'Slovenčina', 'ltr', 'sk'),
        'sl_SI' => $language('sl', 'sl_SI', 'Slovenščina', 'ltr', 'si'),
        'so_SO' => $language('so', 'so_SO', 'Af-Soomaali', 'ltr', 'so'),
        'sq' => $language('sq', 'sq', 'Shqip', 'ltr', 'al'),
        'sr_RS' => $language('sr', 'sr_RS', 'Српски језик', 'ltr', 'rs'),
        'sv_SE' => $language('sv', 'sv_SE', 'Svenska', 'ltr', 'se'),
        'sw' => $language('sw', 'sw', 'Kiswahili', 'ltr', 'ke'),
        'ta_IN' => $language('ta', 'ta_IN', 'தமிழ்', 'ltr', 'in'),
        'th' => $language('th', 'th', 'ไทย', 'ltr', 'th'),
        'tl' => $language('tl', 'tl', 'Tagalog', 'ltr', 'ph'),
        'tr_TR' => $language('tr', 'tr_TR', 'Türkçe', 'ltr', 'tr'),
        'uk' => $language('uk', 'uk', 'Українська', 'ltr', 'ua'),
        'ur' => $language('ur', 'ur', 'اردو', 'rtl', 'pk'),
        'uz_UZ' => $language('uz', 'uz_UZ', 'Oʻzbek', 'ltr', 'uz'),
        'vi' => $language('vi', 'vi', 'Tiếng Việt', 'ltr', 'vn'),
        'zh_CN' => $language('zh', 'zh_CN', '中文 (中国)', 'ltr', 'cn'),
        'zh_HK' => $language('zh', 'zh_HK', '中文 (香港)', 'ltr', 'hk'),
        'zh_TW' => $language('zh', 'zh_TW', '中文 (台灣)', 'ltr', 'tw'),
    ],
];
