# Laranail Toolkit

> A security-first Swiss-army toolkit for Laravel — utilities, traits, middleware, macros, LLM providers, and self-contained feature modules for day-to-day Laravel development.

`laranail/toolkit` bundles the genuinely-reusable building blocks of a Laravel
application behind clean contracts: an LLM provider abstraction (OpenAI / Claude
/ Gemini), an API CRUD generator, an access-log middleware, avatar + gravatar +
captcha + archiver modules, a library of utilities, traits,
macros, and custom Blade directives.

## Support matrix

| | |
|---|---|
| PHP | `^8.3 \|\| ^8.4 \|\| ^8.5` |
| Laravel | `^13.0` |
| License | MIT |
| Author | Imani Manyara — Simtabi LLC |

## Installation

```bash
composer require laranail/toolkit
```

The package auto-registers `ToolkitServiceProvider` via Laravel package
discovery. Publish only what you need:

```bash
# Main config (config/laranail-toolkit.php)
php artisan vendor:publish --tag=laranail-toolkit-config

# Feature-toggles config, migrations, views, lang, CRUD stubs
php artisan vendor:publish --tag=laranail-toolkit-feature-toggles
php artisan vendor:publish --tag=laranail-toolkit-migrations
php artisan vendor:publish --tag=laranail-toolkit-views
php artisan vendor:publish --tag=laranail-toolkit-lang
php artisan vendor:publish --tag=laranail-toolkit-stubs
```

Every utility, trait, and rule can also be published into your app namespace —
see [installation](docs/installation.md) for the full tag list.

## Feature overview

- **LLM providers** — one `LLMProviderInterface`, three drivers (OpenAI, Claude,
  Gemini), selected by config, with retries and typed response objects.
- **`make-crud` command** — generate a Model, API Controller, and Migration from
  a single field spec, with relationships, search, soft deletes, and route
  registration.
- **Artisan commands on `laranail/console`** — all four commands (`make-crud`,
  `ide-helper-macros`, `database`, `tidy`) extend the
  [`laranail/console`](https://opensource.simtabi.com/console/) `^2.5.0` base and
  use its **full feature set**: the fluent `consoleWriter()` (success/error/
  warning/info/note statuses + styling) and the `$this->services` lifecycle —
  performance timing, signal-safe destructive loops, non-interactive-safe
  confirmations, structured + **credential-redacting** logging, and per-run
  metadata. See [Artisan commands](docs/commands.md).
- **`CrudController`** — an abstract base controller with secure pagination,
  search, sorting, and validation out of the box.
- **`access.log` middleware** — terminate-phase request logging with recursive,
  case-insensitive redaction of secrets.
- **Feature modules** (deferred, contract-bound): Avatar, Gravatar, Captcha,
  Archiver.
- **Utilities** — caching, config, feature toggles, filtering, logging,
  pagination, query-parameter parsing, rate limiting, scheduler inspection.
- **Traits** — `ApiResponseTrait`, `Auditable`, `FileProcessingTrait`,
  `HasAvatar`, `HasArchiver`, `HasFormatters`.
- **Macros & Blade** — Str/Arr/Collection/Query/Request/Blueprint macros and a
  set of custom-only Blade directives.
- **Validation** — `reject_common_passwords` rule.

## Quick start

### LLM providers

```php
use Simtabi\Laranail\Toolkit\LLMProviders\Contracts\LLMProviderInterface;

// Provider chosen by config('laranail.toolkit.llm.default_provider')
public function __construct(private LLMProviderInterface $llm) {}

$response = $this->llm->generateResponse(
    modelName: 'gpt-4o-mini',
    messages: [['role' => 'user', 'content' => 'Hello!']],
    temperature: 0.7,
);

echo $response->getContent();
```

### make-crud

```bash
php artisan laranail::toolkit.make-crud Post \
  --fields="title:string:required,body:text:nullable,price:decimal:required|min:0" \
  --belongs-to=User --has-many=Comment \
  --searchable=title,body --soft-deletes --register-routes
# alias: php artisan make:crud Post ...
```

### CrudController

```php
use Simtabi\Laranail\Toolkit\Http\Controllers\CrudController;
use App\Models\Post;

class PostController extends CrudController
{
    public function __construct()
    {
        parent::__construct(new Post());
        $this->searchableFields = ['title', 'body'];
        $this->sortableFields   = ['title', 'created_at'];
        $this->relationships    = ['author'];
    }
}
```

### access.log middleware

```php
// routes/web.php
Route::middleware('access.log')->group(function () {
    // ...
});
```

### Unified entry — the `Toolkit` facade

Each module is reachable three ways: dependency injection (by contract), its own
facade (registered as a Laravel alias — `Avatar`, `Gravatar`, `Captcha`,
`Archiver`), or the unified **`Toolkit`** facade that fronts them all:

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;

$url = Toolkit::gravatar()->setEmail('user@example.com')->setSize(120)->generate();
$ok  = Toolkit::captcha()->verify($token)->isSuccess();
Toolkit::archiver()->extract($zip, $dest);
```

`Toolkit::avatar()/gravatar()/captcha()/archiver()` return the module's typed
service from the container (deferred providers boot on demand). It replaces the
legacy 48-method `Laranail` service-locator (see the
[migration ledger](docs/migration/MIGRATION.md)).

### Avatar (DI or facade)

```php
use Simtabi\Laranail\Toolkit\Modules\Avatar\Contracts\AvatarServiceInterface;

$dataUri = app(AvatarServiceInterface::class)
    ->setName('Imani Manyara')
    ->setSize(128, 128)
    ->generateDataUri();
```

### Gravatar (immutable fluent builder)

```php
use Simtabi\Laranail\Toolkit\Modules\Gravatar\Facades\Gravatar;

$url = Gravatar::setEmail('user@example.com')
    ->setSize(200)
    ->setHttps(true)
    ->generate();
```

### Captcha (fails closed)

```php
use Simtabi\Laranail\Toolkit\Modules\Captcha\Facades\Captcha;

$result = Captcha::verify($request->input('captcha-token'));
if ($result->isSuccess()) { /* ... */ }
```

### Notifications

Multi-channel notifications moved to a dedicated package —
[`laranail/notifications`](https://opensource.simtabi.com/notifications/)
(`composer require laranail/notifications`). It ships the hardened, SSRF-guarded
channels, the typed message DTO, and the channel allow-list.

### Archiver (Zip-Slip hardened)

```php
use Simtabi\Laranail\Toolkit\Modules\Archiver\Facades\Archiver;

Archiver::extract(storage_path('app/release.zip'), storage_path('app/release'));
```

### Utilities

```php
use Simtabi\Laranail\Toolkit\Utilities\PaginationUtil;

$page = PaginationUtil::paginate($items, perPage: 15, currentPage: 1);
```

### Macros

```php
Str::camelToTitle('helloWorld');          // "Hello World"
collect($rows)->toTree('parent_id');      // nested tree
$table->addCommonFields();                // timestamps + soft deletes
```

### Traits

```php
use Simtabi\Laranail\Toolkit\Traits\Auditable;

class Post extends Model
{
    use Auditable; // writes change history to the model_audits table
}
```

## Documentation

| Page | Description |
|------|-------------|
| [Installation](docs/installation.md) | Install, publish tags, requirements |
| [Configuration](docs/configuration.md) | `laranail.toolkit.*` config reference |
| [Architecture](docs/architecture.md) | Modules, deferred providers, layout, migration record |
| [Migration ledger](docs/migration/MIGRATION.md) | Every legacy symbol: migrated / relocated / dropped |
| [Laranail → Toolkit map](docs/migration/laranail-to-toolkit.md) | Every legacy `Laranail::` method → its `Toolkit::` / `Helper::` / native home |
| [LLM providers](docs/llm-providers.md) | OpenAI / Claude / Gemini abstraction |
| [make-crud](docs/make-crud.md) | API CRUD generator command |
| [Artisan commands](docs/commands.md) | The four `laranail::toolkit.*` commands + the full `laranail/console` lifecycle (signal-safe, redacting, shell-safe, path-confined) |
| [CrudController](docs/crud-controller.md) | Secure base controller |
| [Access log](docs/access-log.md) | `access.log` middleware + redaction |
| [API middleware](docs/api-middleware.md) | `api.request` / `api.response` envelope + `BaseRequest` sanitization |
| [Utilities](docs/utilities.md) | The twelve utility classes |
| [Static helpers](docs/helpers.md) | `Helper` — one static facade (array, string, date, system, file, db, geo, console) |
| [Username builder](docs/username.md) | `Support\Username` — fluent, immutable username / handle generator |
| [Security helpers](docs/security.md) | `RejectCommonPasswords` rule — denylist + length/entropy gates + opt-in HIBP k-anonymity |
| [Base classes](docs/base-classes.md) | Reusable controller / job / listener / observer / event bases |
| [Macros](docs/macros.md) | Str/Arr/Collection/Query/Blueprint macros + Blade directives + IDE stub (`ide-helper:macros` regenerator) |
| [Traits](docs/traits.md) | Model & controller traits |
| [Avatar module](docs/modules/avatar.md) | Generated initials avatars |
| [Gravatar module](docs/modules/gravatar.md) | Gravatar URL builder |
| [Captcha module](docs/modules/captcha.md) | reCAPTCHA / hCaptcha / Turnstile |
| [Archiver module](docs/modules/archiver.md) | Safe tar/zip extraction |
| [Atlas module](docs/modules/atlas.md) | Country / currency / timezone / locale data |
| [Livewire module](docs/modules/livewire.md) | Livewire component registration |

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).

- Product: <https://opensource.simtabi.com/toolkit/>
- Documentation: <https://opensource.simtabi.com/toolkit/docs/>
- Source & issues: <https://github.com/laranail/toolkit>
- Security policy: [SECURITY.md](SECURITY.md)
