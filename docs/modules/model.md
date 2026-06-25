# Model module

Eloquent building blocks under `Simtabi\Laranail\Toolkit\Modules\Model`. The
module currently ships one global scope — `Scopes\ArchiveScope` — which, paired
with the `Traits\HasArchiver` trait, gives a model a **soft-archive** lifecycle
keyed off an `archived_at` column. It mirrors Laravel's native soft deletes
(`deleted_at`) so the two can coexist on the same model.

> **Provider-less by design.** The Model module ships **no service provider**
> and registers nothing at boot. `HasArchiver::bootHasArchiver()` registers the
> `ArchiveScope` global scope automatically when you `use HasArchiver` on a
> model — there is nothing to publish or wire up.

## Quick start

```php
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Toolkit\Traits\HasArchiver;

class Post extends Model
{
    use HasArchiver;
}
```

Add the column in a migration (`addCommonFields()` covers soft deletes only, so
add the archive column explicitly):

```php
$table->timestamp('archived_at')->nullable();
```

Now archived rows are hidden by default, and the archive/restore API is
available on both the instance and the query builder.

## The `archived_at` global scope

`final class Modules\Model\Scopes\ArchiveScope implements Scope` adds a global
`whereNull('archived_at')` to every query (so archived rows are excluded by
default) and registers five builder macros via `extend()`:

| Builder macro | Effect |
|---|---|
| `->withArchived(bool $withArchived = true)` | Include archived rows (lifts the global scope); `false` re-applies `withoutArchived()`. |
| `->withoutArchived()` | Explicitly exclude archived rows. |
| `->onlyArchived()` | Return **only** archived rows (`whereNotNull('archived_at')`). |
| `->archive()` | Bulk-archive the matched rows (stamps `archived_at = now()`). |
| `->unArchive()` | Bulk-restore the matched rows (`archived_at = null`). |

```php
Post::all();                 // excludes archived (global scope)
Post::withArchived()->get(); // includes archived
Post::onlyArchived()->get(); // archived only
Post::where('draft', true)->archive(); // bulk-archive
```

The scope qualifies the column when the query has joins, so the macros are safe
in joined queries.

## The `HasArchiver` trait

`Simtabi\Laranail\Toolkit\Traits\HasArchiver` (`@phpstan-require-extends Model`)
boots the scope and supplies the per-instance archive lifecycle. It casts the
archive column to `datetime` on init and exposes:

### Instance methods

| Method | Effect |
|---|---|
| `archive(): ?bool` | Archive this record — fires the `archiving` / `archived` model events; `null` if the record doesn't exist, `false` if `archiving` is cancelled. |
| `unArchive(): ?bool` | Restore this record — fires `unArchiving` / `unArchived`; saves with `archived_at = null`. |
| `runArchive(): void` | The low-level archive query (also touches `updated_at` when timestamps are used). |
| `isArchived(): bool` | Whether `archived_at` is set. |
| `archiver(): ArchiverServiceInterface` | Resolve the file-archiver service (tar/zip) from the container — lets a model expose archive-to-disk helpers without coupling to a concrete implementation. |
| `getArchivedAtColumn(): string` | The archive column name — `archived_at`, or `static::ARCHIVED_AT` when that constant is defined. |
| `getQualifiedArchivedAtColumn(): string` | The table-qualified archive column. |

```php
$post->archive();    // soft-archive (fires archiving/archived events)
$post->isArchived(); // true
$post->unArchive();  // restore
```

### Model events

`HasArchiver` registers four model events you can hook like Eloquent's native
lifecycle events — `archiving`, `archived`, `unArchiving`, `unArchived` — via the
static observer-style registrars:

```php
Post::archiving(fn (Post $post) => /* ... */);
Post::archived(fn (Post $post) => /* ... */);
Post::unArchiving(fn (Post $post) => /* ... */);
Post::unArchived(fn (Post $post) => /* ... */);
```

Returning `false` from an `archiving` / `unArchiving` callback cancels the
operation. The `$archives = true` public property lets a subclass toggle the
behaviour.

> The `archiver()` accessor resolves the file-archiver service documented in the
> [Archiver module](archiver.md). The trait itself is also covered in the
> [Traits](../traits.md) reference.

[← Docs index](../../README.md#documentation)
