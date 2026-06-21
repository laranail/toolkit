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

Defaults applied to `CachingUtil`.

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

Defaults for `RateLimiterUtil` and named profiles.

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
- `laranail.toolkit.notifications` — channels and queue settings (see
  [notifications module](modules/notifications.md)).
- `laranail.toolkit.archiver` — archiver limits.

## Feature toggles

`config/feature-toggles.php` (publish tag
`laranail-toolkit-feature-toggles`) defines flags read by `FeatureToggleUtil`:

```php
return [
    'example_feature' => false,
];
```

Per-user and per-environment overrides are supported via
`feature-toggles.<feature>.user.<id>` and
`feature-toggles.<feature>.environment.<env>`.

[← Docs index](../README.md#documentation)
