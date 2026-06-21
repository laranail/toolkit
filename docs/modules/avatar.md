# Avatar module

Generate avatar images from names, text, or e-mail addresses, with optional
Gravatar integration and a flexible source-resolution pipeline. The service
injects the [Gravatar](gravatar.md) builder plus the cache, and is bound by
contract through a deferred provider (alias `laranail.avatar`, facade `Avatar`).
Image rendering uses `intervention/image`.

Resolve by contract (preferred) or facade:

```php
use Simtabi\Laranail\Toolkit\Modules\Avatar\Contracts\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Facades\Avatar;

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

## Gravatar integration

```php
$svc->setName('Imani')->getGravatarForEmail('user@example.com', size: 200);
$svc->generateWithGravatarFallback(size: 200, preferGravatar: true);
$svc->gravatar('user@example.com'); // returns the Gravatar builder
```

The [`HasAvatar`](../traits.md#hasavatar) trait wires this module to your models.

[← Docs index](../../README.md#documentation)
