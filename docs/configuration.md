# Configuration

The package's main config ships at `config/toolkit.php` and is merged into the
`laranail.toolkit.*` namespace. Publish it with:

```bash
php artisan vendor:publish --tag=laranail-toolkit-config
```

Read any value with `config('laranail.toolkit.<key>')`.

## `laranail.toolkit.llm`

| Key | Default | Notes |
|-----|---------|-------|
| `default_provider` | `openai` | `openai` \| `gemini` \| `claude` — chooses the driver bound to `LLMProviderInterface`. Env: `LLM_DEFAULT_PROVIDER`. |

## `laranail.toolkit.cache`

Defaults applied to `CacheService`.

| Key | Default |
|-----|---------|
| `default_expiration` | `60` (minutes) |
| `default_tags` | `[]` |

## `laranail.toolkit.access_log`

| Key | Default | Notes |
|-----|---------|-------|
| `enabled` | `true` | Toggle persistence of the `access.log` middleware. Env: `LARANAIL_ACCESS_LOG_ENABLED`. |
| `redact` | `null` | Request keys to redact. `null` uses the middleware's built-in deny-list. |

## `laranail.toolkit.rate_limiting`

Defaults for `RateLimiterService` and named profiles.

| Key | Default |
|-----|---------|
| `default_max_attempts` | `60` |
| `default_decay_minutes` | `1` |
| `cache_prefix` | `rate_limit:` |
| `defaults.api` | `60 attempts / 1 min` |
| `defaults.auth` | `5 attempts / 15 min` |
| `defaults.download` | `3 attempts / 1 min` |

## LLM provider keys

Each provider has its own block. Keys are read when the matching driver is
resolved.

### `laranail.toolkit.openai`

`api_key` (`OPENAI_API_KEY`), `max_retries` (3), `retry_delay` (2),
`default_model` (`gpt-3.5-turbo`), `default_temperature` (0.7),
`default_max_tokens` (300), `default_top_p` (1.0).

### `laranail.toolkit.gemini`

`api_key` (`GEMINI_API_KEY`), `max_retries` (3), `retry_delay` (2),
`base_url` (`https://generativelanguage.googleapis.com/v1beta`),
`default_model` (`gemini-2.0-flash`), and matching temperature/tokens/top-p
defaults.

### `laranail.toolkit.claude`

`api_key` (`CLAUDE_API_KEY`), `max_retries` (3), `retry_delay` (2),
`base_url` (`https://api.anthropic.com`),
`default_model` (`claude-3-5-sonnet-20241022`), `default_temperature` (1.0),
`default_max_tokens` (1024), `default_top_p` (1.0).

## Module configs

The feature modules merge their own config files under the same namespace:

- `laranail.toolkit.captcha` — providers and behavior (see
  [captcha module](modules/captcha.md)).
- `laranail.toolkit.archiver` — archiver limits.
- `laranail.toolkit.atlas` — one self-contained file for the Atlas module:
  select-box / cache settings, the continent display-name map
  (`atlas.continents`), and the Laravel-locale registry (`atlas.languages`)
  (see [atlas module](modules/atlas.md)). Publishes under the
  `laranail-toolkit-atlas` tag to `config/laranail-toolkit-atlas.php`.

> Multi-channel notifications now live in the separate
> [`laranail/notifications`](https://opensource.simtabi.com/notifications/) package,
> with its own `config/notifications.php`.

## Feature toggles

`config/feature-toggles.php` (publish tag
`laranail-toolkit-feature-toggles`) defines flags read by `FeatureToggle`:

```php
return [
    'example_feature' => false,
];
```

Per-user and per-environment overrides are supported via
`feature-toggles.<feature>.user.<id>` and
`feature-toggles.<feature>.environment.<env>`.

## Security data

`config/security.php` is the single, merged source for the package's bundled
security datasets, read lazily by
`Simtabi\Laranail\Toolkit\Modules\Security\SecurityData`:

- `passwords.common` — 560 lowercased, deduplicated common passwords
  (`RejectCommonPasswords`).
- `passphrases.wordlist` — exactly 7776 EFF CC0 words (`Passphrase`).
- `redact_keys` — default request-data redaction keys (`AccessLogMiddleware`).

`SecurityData` loads the package default via a `__DIR__`-relative path and works
without a booted Laravel app; when Laravel is booted and a published override is
present, it prefers that file. Publish an override copy with:

```bash
php artisan vendor:publish --tag=laranail-toolkit-security
```

which writes `config/laranail-toolkit-security.php`. See the
[security helpers](security.md#merged-security-data-configsecurityphp) doc for
details.

[← Docs index](../README.md#documentation)
