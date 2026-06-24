# Base classes

Five abstract base classes that bundle the boilerplate of common Laravel
building blocks behind sensible defaults. Extend them so your concrete
controllers, jobs, listeners, observers, and events only carry their own logic.

| Base class | Extend it to… |
|------------|---------------|
| `Http\Controllers\BaseController` | …build a controller with auth + validation + API responses ready. |
| `Jobs\BaseJob` | …queue work with retries, backoff, timeout, and failure logging. |
| `Listeners\BaseListener` | …handle an event with a built-in "should I run?" gate. |
| `Observers\BaseObserver` | …observe only the model events you care about. |
| `Events\BaseEvent` | …dispatch/broadcast an event without re-declaring trait boilerplate. |

## BaseController

`abstract class BaseController extends Illuminate\Routing\Controller`

Mixes in `ApiResponseTrait` (consistent `success`/`error` JSON envelopes),
`AuthorizesRequests` (`$this->authorize(...)`), and `ValidatesRequests`
(`$this->validate(...)`). Extend it for any controller that wants those three out
of the box.

```php
use Simtabi\Laranail\Toolkit\Http\Controllers\BaseController;

class ReportController extends BaseController
{
    public function show(Request $request): JsonResponse
    {
        $this->authorize('view-reports');
        $data = $this->validate($request, ['from' => 'required|date']);

        return $this->successResponse($report); // from ApiResponseTrait
    }
}
```

> For full CRUD scaffolding (pagination, search, sorting) use the richer
> [`CrudController`](crud-controller.md); `BaseController` is the lean base for
> bespoke controllers.

## BaseJob

`abstract class BaseJob implements ShouldQueue`

Uses `Dispatchable`, `InteractsWithQueue`, `Queueable`, and `SerializesModels`,
and ships resilient defaults: `$tries = 3`, `$backoff = 10` (seconds),
`$timeout = 120` (seconds). `failed(Throwable $e)` logs the exhausted job via
`LoggingUtil` — override it for bespoke cleanup (call `parent::failed($e)` to keep
the logging).

```php
use Simtabi\Laranail\Toolkit\Jobs\BaseJob;

class ImportFeed extends BaseJob
{
    public function handle(): void { /* … */ }
}

ImportFeed::dispatch(); // 3 tries, 10s backoff, 120s timeout, auto-logged on failure
```

## BaseListener

`abstract class BaseListener`

Centralises the "should this listener run?" decision. `handle()` consults
`shouldHandle()` (defaults to `true`) and short-circuits when it returns `false`;
otherwise it calls your `handleEvent()`. Override `shouldHandle()` for feature
flags, environment, or payload gating without repeating the guard in every
listener.

```php
use Simtabi\Laranail\Toolkit\Listeners\BaseListener;

class SendWelcome extends BaseListener
{
    protected function shouldHandle(object $event): bool
    {
        return $event->user->wantsEmail;
    }

    protected function handleEvent(object $event): void { /* … */ }
}
```

## BaseObserver

`abstract class BaseObserver`

Declares all twelve Eloquent lifecycle hooks (`retrieved`, `creating`, `created`,
`updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`, `restoring`,
`restored`, `forceDeleted`) as no-ops. Extend it and override **only** the events
you need — no empty stubs.

```php
use Simtabi\Laranail\Toolkit\Observers\BaseObserver;

class PostObserver extends BaseObserver
{
    public function saving(Model $model): void
    {
        $model->slug ??= str($model->title)->slug();
    }
}
```

## BaseEvent

`abstract class BaseEvent`

Uses `Dispatchable`, `InteractsWithSockets`, and `SerializesModels` — the standard
event trait trio — so a concrete event only carries its payload.

```php
use Simtabi\Laranail\Toolkit\Events\BaseEvent;

class OrderShipped extends BaseEvent
{
    public function __construct(public readonly Order $order) {}
}

OrderShipped::dispatch($order);
```

[← Docs index](../README.md#documentation)
