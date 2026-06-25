# Avatar module

Generate avatar images from names, text, or e-mail addresses, with optional
Gravatar integration and a flexible source-resolution pipeline. The service
injects the [Gravatar](gravatar.md) builder plus the cache, and is bound by
contract through a deferred provider (alias `laranail.avatar`, facade `Avatar`).
Image rendering uses `intervention/image`.

Resolve by contract (preferred) or facade:

```php
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Avatar;

$svc = app(AvatarServiceInterface::class);
```

## Generate from a name

The service is fluent — every setter returns `self`.

```php
$dataUri = $svc
    ->setName('Imani Manyara')
    ->setSize(128, 128)            // width, height
    ->setShape('circle')
    ->setColors('#1f2937', '#ffffff')
    ->generateDataUri();          // data:image/...;base64,...
```

Generation methods:

| Method | Returns |
|--------|---------|
| `generate()` | Raw encoded image string. |
| `generateBase64()` | Base64 string. |
| `generateDataUri()` | `data:` URI for `<img src>`. |
| `save(string $path): bool` | Write to disk. |
| `getImageObject(): ImageInterface` | The Intervention image. |
| `makeInitials(): string` | The computed initials. |

Common setters include `setName`, `setWidth`, `setHeight`, `setSize`,
`setShape`, `setBackgroundColor`, `setForegroundColor`, `setColors`, `setChars`,
`setFontSize`, `setBorderSize`, `setBorderColor`, `setUppercase`, `setAscii`,
`setCacheEnabled`, `setCacheTtl`, `setFontPath`, `setQuality`. Fonts can be
chosen with `useFont(AvatarFont)`, `useFontByName(string)`, or `useDefaultFont()`.

Discover the supported palette with `getAvailableShapes()`,
`getAvailableBackgroundColors()`, `getAvailableForegroundColors()`,
`getAvailableFonts()`, and `isImageProcessingAvailable()`.

## Fonts

Text is rendered with one of the bundled TrueType fonts shipped under
`resources/assets/fonts/`, modelled by the native `AvatarFont` string enum
(each case is backed by its `.ttf` filename):

| Case | File | Category | Display name |
|---|---|---|---|
| `AvatarFont::ROBOTO_BOLD` | `Roboto-Bold.ttf` | sans-serif | Roboto Bold (**default**) |
| `AvatarFont::FREE_SERIF` | `FreeSerif.ttf` | serif | Free Serif |
| `AvatarFont::MSYH` | `msyh.ttf` | international | Microsoft YaHei (CJK) |

```php
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarFont;

$svc->useFont(AvatarFont::FREE_SERIF);
$svc->useFontByName('msyh.ttf');     // by backing filename
$svc->useDefaultFont();              // AvatarFont::default() === ROBOTO_BOLD

AvatarFont::MSYH->getDisplayName();       // "Microsoft YaHei"
AvatarFont::MSYH->getCategory();          // "international"
AvatarFont::MSYH->supportsUnicode();      // true
AvatarFont::names();                      // ['ROBOTO_BOLD','FREE_SERIF','MSYH']
AvatarFont::values();                     // ['Roboto-Bold.ttf', ...]
AvatarFont::getByCategory('serif');       // [AvatarFont::FREE_SERIF]
AvatarFont::fromValueOrNull('nope.ttf');  // null
```

All three fonts are Unicode-capable; `MSYH` adds CJK coverage. Because only
bundled fonts are referenced, no system-font path is ever read.

## Resolve from any source

`getAvatar()` accepts a string (name or e-mail), an Eloquent model, or a
callback, and returns an immutable `AvatarResolution`:

```php
$resolution = $svc->getAvatar($user);          // AvatarResolution
$resolution->getUrl();                          // resolved URL / data URI
$resolution->isGravatar();                      // how it was resolved
$resolution->getDescription();                  // human-readable

$url = $svc->getAvatarUrl('user@example.com');  // shorthand for ->getUrl()
$cached = $svc->getAvatarCached($user, ttl: 3600);
```

`AvatarResolution` exposes `getUrl()`, `getSourceType()`, `getMethod()`,
`getMetadata()`, and predicates (`isGravatar()`, `isInitials()`, `isUrl()`,
`isFallback()`, `isFromModel()`, `isFromEmail()`, …) plus `toArray()`.

### Resolution order (models)

When the source is an Eloquent model, the resolver walks a fallback chain and
records the winning step as the resolution's `method`:

1. **Stored avatar URL** (`method: url`) — when `prefer_model_avatar` is on and
   the model has an avatar column.
2. **Gravatar** (`method: gravatar`) — when `prefer_gravatar` is on and the
   model has an e-mail.
3. **Initials** (`method: initials`) — when `fallback_to_initials` is on and the
   model has a name.
4. **Generic fallback** (`method: fallback`) — a placeholder initials avatar
   built from `fallback_name`.

Columns are discovered through case-insensitive **field mappings** (so common
column names work with no configuration):

| Logical field | Candidate columns (in order) |
|---|---|
| `email` | `email`, `email_address`, `user_email` |
| `name` | `name`, `full_name`, `display_name`, `username` |
| `avatar_url` | `avatar`, `avatar_url`, `profile_picture`, `photo_url` |
| `first_name` | `first_name`, `firstname`, `given_name` |
| `last_name` | `last_name`, `lastname`, `family_name` |

Override any of this per call via the `$options` array (merged over the
defaults):

```php
$svc->getAvatar($user, [
    'prefer_gravatar'   => false,   // skip Gravatar, go straight to initials
    'default_size'      => 256,
    'default_https'     => true,
    'fallback_name'     => 'Guest',
    'fallback_shape'    => 'circle',
    'cache_avatars'     => true,
    'cache_ttl'         => 3600,
]);
```

### Resolve with a callback

Pass a `Closure` to take full control. It receives an
`AvatarResolutionContextData` exposing the avatar/gravatar builders and result
factories, and returns an `AvatarResolution`:

```php
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolutionContextData;

$resolution = $svc->getAvatar(function (AvatarResolutionContextData $ctx) use ($user) {
    if ($user->isPremium()) {
        return $ctx->createGravatarResult($user->email, size: 256);
    }

    return $ctx->createInitialsResult($user->name, size: 256);
});
```

Context helpers: `avatar()`, `gravatar()`, `generateGravatar()`,
`generateCustom()`, `createResult()`, `createGravatarResult()`,
`createInitialsResult()`, `createCustomResult()`, `createUrlResult()`, plus
`getConfig()` / `hasConfig()` / `getAllConfig()` for the merged options.

## Gravatar integration

```php
$svc->getGravatar($user, size: 200);                       // Gravatar URL for a model's e-mail
$svc->getGravatarForEmail('user@example.com', size: 200);  // for an explicit address
$svc->hasGravatar($user);                                  // bool — does the model expose an e-mail?
$svc->generateWithGravatarFallback(size: 200, preferGravatar: true);
$svc->gravatar('user@example.com');                        // the underlying Gravatar builder

$svc->getGravatarRatings();        // ['g', 'pg', 'r', 'x']
$svc->getGravatarDefaultImages();  // ['mp', 'identicon', 'monsterid', ...]
```

The [`HasAvatar`](../traits.md#hasavatar) trait wires this module to your models.

[← Docs index](../../README.md#documentation)
