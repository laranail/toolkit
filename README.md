# laranail/toolkit

[![Tests](https://github.com/laranail/toolkit/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/toolkit/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

`laranail/toolkit` is not published to Packagist, so there is no registry-version badge to show: see [Install](#install).

> A security-first Swiss-army toolkit for Laravel — an LLM provider abstraction (OpenAI / Claude / Gemini), an API CRUD generator, an access-log middleware, captcha and archiver modules, and a library of utilities, traits, macros, and custom Blade directives behind clean contracts.

Compatible with PHP `^8.4.1 || ^8.5` and Laravel `^13.0`.

## Install

```bash
# laranail/toolkit and laranail/console are not on Packagist yet: point Composer at GitHub.
composer config repositories.laranail-console vcs https://github.com/laranail/console
composer config repositories.laranail-toolkit vcs https://github.com/laranail/toolkit
composer require laranail/toolkit:^0.2
```

## Quick start guide and usage

### Getting started

`ToolkitServiceProvider` is auto-discovered, and its migrations, views and translations load
from the package. Two steps remain:

1. Publish the config if you want to own it (it lands under `config/laranail/toolkit*`):

   ```bash
   php artisan vendor:publish --tag=laranail::toolkit-config
   ```

2. Run the migrations, which create the `access_logs` and `model_audits` tables:

   ```bash
   php artisan migrate
   ```

### Usage

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;

// The authenticated user on a named guard, or null.
$admin = Toolkit::user('admin');

// A 20-character password, every character class, no ambiguous glyphs.
$password = Toolkit::password()->generate();

// Extract an archive, picking the extractor from its extension.
Toolkit::archiver()->extract(storage_path('app/imports/catalogue.zip'), storage_path('app/imports/catalogue'));
```

The full walkthrough is in [Getting started](docs/getting-started.md); everything else is in the [documentation index](#documentation).

## <a name="documentation"></a>Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/toolkit](https://opensource.simtabi.com/documentation/laranail/toolkit/)** — the feature overview, LLM providers, the API CRUD generator + middleware, the feature modules, the guard-aware authenticated-user accessors, the unified cache (data + maintenance), the fluent runtime config manager, the Python microservice HTTP client, the PHP runtime/INI configurator, and the per-model macro registry, and the utilities/traits/macros/directives reference.

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
