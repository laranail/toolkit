# CrudController

`Simtabi\Laranail\Toolkit\Http\Controllers\CrudController` is an abstract base
controller that gives any Eloquent model a secure, paginated JSON API with no
boilerplate. (The [make-crud](make-crud.md) command generates standalone
controllers; this base class is the runtime alternative for hand-written ones.)

## Usage

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
        $this->relationships    = ['author', 'comments'];
        $this->validationRules  = [
            'title' => 'required|string|max:255',
            'body'  => 'nullable|string',
        ];
        $this->perPage    = 15;
        $this->maxPerPage = 100;
    }
}
```

Wire it as an `apiResource`:

```php
Route::apiResource('posts', PostController::class);
```

## Configurable properties

| Property | Default | Purpose |
|----------|---------|---------|
| `$model` | (constructor) | The Eloquent model instance to operate on. |
| `$validationRules` | `[]` | Rules for store/update. Empty falls back to the model's fillable. |
| `$searchableFields` | `[]` | Columns the `search` query param matches against. |
| `$relationships` | `[]` | Relations eager-loaded on index/show/update. |
| `$sortableFields` | `[]` | Whitelist for `sort_by`. Empty disables sorting. |
| `$perPage` | `15` | Default page size. |
| `$maxPerPage` | `100` | Upper bound for client-requested `per_page`. |

## Actions

| Method | Verb | Behaviour |
|--------|------|-----------|
| `getAllRecords(Request $request): JsonResponse` | index | Paginated list with optional search, eager loading, sorting; returns `data` + `meta` (current_page, last_page, per_page, total). |
| `getRecordById($id): JsonResponse` | show | Single record with relationships. |
| `storeRecord(Request $request): JsonResponse` | store | Validates, creates, returns `201`. |
| `updateRecord(Request $request, $id): JsonResponse` | update | Validates, updates, reloads relationships. |
| `deleteRecord($id): JsonResponse` | destroy | Deletes, returns `204`. |

## Security

The base controller is hardened the same way the generated controller is:

- **Search** escapes LIKE wildcards so user input cannot broaden the match.
- **Sorting** is restricted to `$sortableFields`; an empty list disables sorting
  entirely (no SQL injection via `sort_by`).
- **`per_page`** is clamped to `[1, $maxPerPage]` by `resolvePerPage()`.
- **Mass assignment** — when no explicit `$validationRules` are set,
  `validateRequest()` restricts input to the model's fillable attributes; an
  empty fillable means nothing is mass-assignable. On update, `unique:` rules are
  rewritten to ignore the current record.

[← Docs index](../README.md#documentation)
