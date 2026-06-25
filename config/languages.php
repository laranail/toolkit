<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Atlas language / locale registry
|--------------------------------------------------------------------------
|
| Ported (de-bloated) from the legacy Atlas\Languages::$languages map. The
| data package (rinvex/countries) is country-centric, so this Laravel-locale
| shaped table is kept here as a resource: each entry is keyed by its Laravel
| locale and carries the slim tuple needed to render a locale switcher.
|
| Each value:
|   'iso639_1'      => ISO 639-1 language code
|   'locale'        => Laravel locale (mirrors the key)
|   'native_name'   => endonym (the language's own name)
|   'dir'           => text direction ('ltr' | 'rtl')
|   'flag'          => flag code (region/country code or a special label)
|
| @return array<string, array{iso639_1: string, locale: string, native_name: string, dir: string, flag: string}>
*/

$make = static fn (string $iso639_1, string $locale, string $native, string $dir, string $flag): array => [
    'iso639_1' => $iso639_1,
    'locale' => $locale,
    'native_name' => $native,
    'dir' => $dir,
    'flag' => $flag,
];

return [
    'af' => $make('af', 'af', 'Afrikaans', 'ltr', 'za'),
    'ar' => $make('ar', 'ar', 'العربية', 'rtl', 'ar'),
    'ary' => $make('ar', 'ary', 'العربية المغربية', 'rtl', 'ma'),
    'az' => $make('az', 'az', 'Azərbaycan', 'ltr', 'az'),
    'azb' => $make('az', 'azb', 'گؤنئی آذربایجان', 'rtl', 'az'),
    'bel' => $make('be', 'bel', 'Беларуская мова', 'ltr', 'by'),
    'bg_BG' => $make('bg', 'bg_BG', 'български', 'ltr', 'bg'),
    'bn_BD' => $make('bn', 'bn_BD', 'বাংলা', 'ltr', 'bd'),
    'bo' => $make('bo', 'bo', 'བོད་སྐད', 'ltr', 'bo'),
    'bs_BA' => $make('bs', 'bs_BA', 'Bosanski', 'ltr', 'ba'),
    'ceb' => $make('ceb', 'ceb', 'Cebuano', 'ltr', 'ph'),
    'cs_CZ' => $make('cs', 'cs_CZ', 'Čeština', 'ltr', 'cz'),
    'cy' => $make('cy', 'cy', 'Cymraeg', 'ltr', 'gb-wls'),
    'da_DK' => $make('da', 'da_DK', 'Dansk', 'ltr', 'dk'),
    'de_CH' => $make('de', 'de_CH', 'Deutsch', 'ltr', 'ch'),
    'de_DE' => $make('de', 'de_DE', 'Deutsch', 'ltr', 'de'),
    'el' => $make('el', 'el', 'Ελληνικά', 'ltr', 'gr'),
    'en_AU' => $make('en', 'en_AU', 'English', 'ltr', 'au'),
    'en_CA' => $make('en', 'en_CA', 'English', 'ltr', 'ca'),
    'en_GB' => $make('en', 'en_GB', 'English', 'ltr', 'gb'),
    'en_NZ' => $make('en', 'en_NZ', 'English', 'ltr', 'nz'),
    'en_US' => $make('en', 'en_US', 'English', 'ltr', 'us'),
    'en_ZA' => $make('en', 'en_ZA', 'English', 'ltr', 'za'),
    'es_AR' => $make('es', 'es_AR', 'Español', 'ltr', 'ar'),
    'es_CL' => $make('es', 'es_CL', 'Español', 'ltr', 'cl'),
    'es_CO' => $make('es', 'es_CO', 'Español', 'ltr', 'co'),
    'es_ES' => $make('es', 'es_ES', 'Español', 'ltr', 'es'),
    'es_MX' => $make('es', 'es_MX', 'Español', 'ltr', 'mx'),
    'es_PE' => $make('es', 'es_PE', 'Español', 'ltr', 'pe'),
    'es_VE' => $make('es', 'es_VE', 'Español', 'ltr', 've'),
    'et' => $make('et', 'et', 'Eesti', 'ltr', 'ee'),
    'eu' => $make('eu', 'eu', 'Euskara', 'ltr', 'es'),
    'fa_IR' => $make('fa', 'fa_IR', 'فارسی', 'rtl', 'ir'),
    'fi' => $make('fi', 'fi', 'Suomi', 'ltr', 'fi'),
    'fr_BE' => $make('fr', 'fr_BE', 'Français', 'ltr', 'be'),
    'fr_FR' => $make('fr', 'fr_FR', 'Français', 'ltr', 'fr'),
    'gd' => $make('gd', 'gd', 'Gàidhlig', 'ltr', 'gb-sct'),
    'gl_ES' => $make('gl', 'gl_ES', 'Galego', 'ltr', 'es'),
    'gu' => $make('gu', 'gu', 'ગુજરાતી', 'ltr', 'in'),
    'he_IL' => $make('he', 'he_IL', 'עברית', 'rtl', 'il'),
    'hi_IN' => $make('hi', 'hi_IN', 'हिन्दी', 'ltr', 'in'),
    'hr' => $make('hr', 'hr', 'Hrvatski', 'ltr', 'hr'),
    'hu_HU' => $make('hu', 'hu_HU', 'Magyar', 'ltr', 'hu'),
    'hy' => $make('hy', 'hy', 'Հայերեն', 'ltr', 'am'),
    'id_ID' => $make('id', 'id_ID', 'Bahasa Indonesia', 'ltr', 'id'),
    'is_IS' => $make('is', 'is_IS', 'Íslenska', 'ltr', 'is'),
    'it_IT' => $make('it', 'it_IT', 'Italiano', 'ltr', 'it'),
    'ja' => $make('ja', 'ja', '日本語', 'ltr', 'jp'),
    'ka_GE' => $make('ka', 'ka_GE', 'ქართული', 'ltr', 'ge'),
    'kk' => $make('kk', 'kk', 'Қазақ тілі', 'ltr', 'kz'),
    'km' => $make('km', 'km', 'ភាសាខ្មែរ', 'ltr', 'kh'),
    'ko_KR' => $make('ko', 'ko_KR', '한국어', 'ltr', 'kr'),
    'lo' => $make('lo', 'lo', 'ພາສາລາວ', 'ltr', 'la'),
    'lt_LT' => $make('lt', 'lt_LT', 'Lietuviškai', 'ltr', 'lt'),
    'lv' => $make('lv', 'lv', 'Latviešu valoda', 'ltr', 'lv'),
    'mk_MK' => $make('mk', 'mk_MK', 'македонски јазик', 'ltr', 'mk'),
    'mn' => $make('mn', 'mn', 'Монгол хэл', 'ltr', 'mn'),
    'mr' => $make('mr', 'mr', 'मराठी', 'ltr', 'in'),
    'ms_MY' => $make('ms', 'ms_MY', 'Bahasa Melayu', 'ltr', 'my'),
    'my_MM' => $make('my', 'my_MM', 'ဗမာစာ', 'ltr', 'mm'),
    'nb_NO' => $make('nb', 'nb_NO', 'Norsk Bokmål', 'ltr', 'no'),
    'ne_NP' => $make('ne', 'ne_NP', 'नेपाली', 'ltr', 'np'),
    'nl_NL' => $make('nl', 'nl_NL', 'Nederlands', 'ltr', 'nl'),
    'nn_NO' => $make('nn', 'nn_NO', 'Norsk Nynorsk', 'ltr', 'no'),
    'pl_PL' => $make('pl', 'pl_PL', 'Polski', 'ltr', 'pl'),
    'ps' => $make('ps', 'ps', 'پښتو', 'rtl', 'af'),
    'pt_BR' => $make('pt', 'pt_BR', 'Português', 'ltr', 'br'),
    'pt_PT' => $make('pt', 'pt_PT', 'Português', 'ltr', 'pt'),
    'ro_RO' => $make('ro', 'ro_RO', 'Română', 'ltr', 'ro'),
    'ru_RU' => $make('ru', 'ru_RU', 'Русский', 'ltr', 'ru'),
    'si_LK' => $make('si', 'si_LK', 'සිංහල', 'ltr', 'lk'),
    'sk_SK' => $make('sk', 'sk_SK', 'Slovenčina', 'ltr', 'sk'),
    'sl_SI' => $make('sl', 'sl_SI', 'Slovenščina', 'ltr', 'si'),
    'so_SO' => $make('so', 'so_SO', 'Af-Soomaali', 'ltr', 'so'),
    'sq' => $make('sq', 'sq', 'Shqip', 'ltr', 'al'),
    'sr_RS' => $make('sr', 'sr_RS', 'Српски језик', 'ltr', 'rs'),
    'sv_SE' => $make('sv', 'sv_SE', 'Svenska', 'ltr', 'se'),
    'sw' => $make('sw', 'sw', 'Kiswahili', 'ltr', 'ke'),
    'ta_IN' => $make('ta', 'ta_IN', 'தமிழ்', 'ltr', 'in'),
    'th' => $make('th', 'th', 'ไทย', 'ltr', 'th'),
    'tl' => $make('tl', 'tl', 'Tagalog', 'ltr', 'ph'),
    'tr_TR' => $make('tr', 'tr_TR', 'Türkçe', 'ltr', 'tr'),
    'uk' => $make('uk', 'uk', 'Українська', 'ltr', 'ua'),
    'ur' => $make('ur', 'ur', 'اردو', 'rtl', 'pk'),
    'uz_UZ' => $make('uz', 'uz_UZ', 'Oʻzbek', 'ltr', 'uz'),
    'vi' => $make('vi', 'vi', 'Tiếng Việt', 'ltr', 'vn'),
    'zh_CN' => $make('zh', 'zh_CN', '中文 (中国)', 'ltr', 'cn'),
    'zh_HK' => $make('zh', 'zh_HK', '中文 (香港)', 'ltr', 'hk'),
    'zh_TW' => $make('zh', 'zh_TW', '中文 (台灣)', 'ltr', 'tw'),
];
