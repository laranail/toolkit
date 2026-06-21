# Traits

Drop-in traits under `Simtabi\Laranail\Toolkit\Traits` for models and
controllers.

## ApiResponseTrait

Standardised JSON responses for API controllers. Use it in a controller (or
publish it into `app/Traits/` with the `laranail-toolkit-api-response-trait`
tag).

```php
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;

class PostController
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->successResponse($posts, 'Posts fetched.');
    }
}
```

| Method | Signature |
|--------|-----------|
| `successResponse` | `(mixed $data = null, string $message = 'Request successful.', int $statusCode = 200, array $meta = []): JsonResponse` |
| `errorResponse` | `(string $message = 'Something went wrong.', int $statusCode = 500, array $errors = [], mixed $debug = null): JsonResponse` (debug only when `app.debug`) |
| `exceptionResponse` | `(\Throwable $e, int $statusCode = 500): JsonResponse` (logs + centralised handling) |
| `paginatedResponse` | `($paginator, string $message = 'Data fetched successfully.'): JsonResponse` |

## Auditable

Automatic change history for an Eloquent model. Hooks `created`, `updated`, and
`deleted`, writing rows to the **`model_audits`** table (`model_type`,
`model_id`, `event`, `old_values`, `new_values`, `user_id`, timestamps).

```php
use Simtabi\Laranail\Toolkit\Traits\Auditable;

class Post extends Model
{
    use Auditable;
}
```

**Security:** attributes listed in the model's `$hidden` (passwords, tokens, …)
are masked as `[REDACTED]` before being persisted to the audit table.

## FileProcessingTrait

Upload / fetch / delete files on the configured filesystem with
directory-traversal protection (via `Support\FilePathGuard`).

```php
use Simtabi\Laranail\Toolkit\Traits\FileProcessingTrait;

$name  = $this->uploadFile($request->file('doc'), 'uploads');
$names = $this->uploadFiles($request->file('docs'));
$body  = $this->getFile($name, 'uploads');
$this->deleteFile($name, 'uploads');
$this->deleteFiles($names);
```

## HasAvatar

Avatar resolution for a user-like model, wired to the Avatar and Gravatar
modules. Override `avatarEmailAttribute()` / `avatarAttribute()` to point at
custom columns.

```php
use Simtabi\Laranail\Toolkit\Traits\HasAvatar;

class User extends Authenticatable
{
    use HasAvatar;
}

$user->getAvatar();                    // stored avatar, else Gravatar fallback
$user->gravatar(size: 128);            // Gravatar URL or null
$user->generateAvatar(size: 128);      // initials avatar data URI
$user->getAvatarWithFallback(128, preferGravatar: true);
```

## HasArchiver

Soft-archive on an `archived_at` column — independent of Laravel's `deleted_at`
soft deletes. Registers the `ArchiveScope` global scope (hiding archived rows by
default) and builder helpers `withArchived()`, `onlyArchived()`,
`withoutArchived()`. Fires `archiving` / `archived` / `unArchiving` /
`unArchived` events.

```php
use Simtabi\Laranail\Toolkit\Traits\HasArchiver;

class Document extends Model
{
    use HasArchiver; // requires an `archived_at` timestamp column
}

$document->archive();
$document->isArchived();   // true
$document->unArchive();

Document::onlyArchived()->get();
```

The trait also exposes `archiver()` — the file `ArchiverServiceInterface` (tar /
zip) resolved from the container (see [archiver module](modules/archiver.md)).

## HasFormatters

Presentation helpers built on native `Carbon` / `Str`.

```php
use Simtabi\Laranail\Toolkit\Traits\HasFormatters;

$model->formattedCreatedAt();          // default 'm/d/Y h:i:s a'
$model->formattedUpdatedAt('Y-m-d');
$model->formattedFullName();           // first_name + last_name
$model->formattedUsername();           // "@handle" or false
$model->excerpt(150);                  // truncated `content`
```

[← Docs index](../README.md#documentation)
