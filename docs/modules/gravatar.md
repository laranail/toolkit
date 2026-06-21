# Gravatar module

An **immutable, fluent builder** for Gravatar URLs. Every `set*` method returns a
new instance, so a container-resolved/shared builder can be reused safely without
state leaking between calls. Bound by contract through a deferred provider (alias
`laranail.gravatar`, facade `Gravatar`).

```php
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Contracts\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Facades\Gravatar;
```

## Build a URL

```php
$url = Gravatar::setEmail('user@example.com')
    ->setSize(200)
    ->setHttps(true)
    ->setRating('g')
    ->setDefaultImage('monsterid')
    ->generate();
```

Or via the contract:

```php
$url = app(GravatarServiceInterface::class)
    ->setEmail('user@example.com')
    ->setSize(128)
    ->generate();
```

## Setters (all return a new instance)

| Setter | |
|--------|---|
| `setEmail(string $email)` | The e-mail to hash. |
| `setSize(int $size)` | Pixel size. |
| `setHttps(bool $https)` | Secure vs. plain host. |
| `setRating(string $rating)` | `g`, `pg`, `r`, `x`. |
| `setDefaultImage(string $image)` | Fallback image style (e.g. `monsterid`, `mp`). |
| `setForceDefault(bool $force)` | Always show the default image. |
| `setCustomDefaultUrl(?string $url)` | Custom fallback image URL. |

Getters mirror each setter (`getEmail()`, `getSize()`, `isHttps()`,
`getRating()`, `getDefaultImage()`, `isForceDefault()`, `getCustomDefaultUrl()`).
Discover valid values with `availableRatings()` and `availableDefaultImages()`.
Helpers: `isValidEmail(string $email): bool` and `hashEmail(string $email): string`.

## Build or resolve

| Method | |
|--------|---|
| `generate(): string` | The Gravatar URL for the configured e-mail. |
| `resolve(): GravatarResolution` | A structured result describing the resolved Gravatar. |

`GravatarResolution` carries `url`, `email`, `hash`, `size`, `isHttps`,
`rating`, `defaultImage`, with `isSecure()`, `isAppropriate()`, `domain()`, and
`toArray()`.

```php
$result = Gravatar::setEmail('user@example.com')->resolve();
$result->url;
$result->isAppropriate(); // rating g/pg
```

[← Docs index](../../README.md#documentation)
