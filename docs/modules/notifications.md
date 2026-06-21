# Notifications module

A multi-channel notification dispatcher. Bound through a deferred provider (alias
`laranail.notifications`, facade `Notifications`). Channels are resolved from a
fixed enum allow-list and can optionally be queued.

```php
use Simtabi\Laranail\Toolkit\Modules\Notifications\Facades\Notifications;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Services\NotificationService;
```

## Channels

Twelve built-in channels (the `NotificationChannel` enum):

`log`, `email`, `database`, `cache`, `file`, `console`, `webhook`, `slack`,
`discord`, `sms`, `push`, `apple_business_messages`.

The HTTP-bound channels — `webhook`, `slack`, `discord`, `push`,
`apple_business_messages` — are subject to the SSRF guard (below).

## Send

```php
// Simple body to specific channels
Notifications::send('Deployment finished', channels: ['slack', 'log']);

// With data + level, default channels from config
Notifications::send('Build {status}', data: ['status' => 'green'], level: 'info');

// Broadcast to all default channels
Notifications::broadcast('Maintenance window starts at 02:00');
```

`send()` returns a `NotificationResult`:

```php
$result = Notifications::send('Hello', channels: ['slack', 'email']);

$result->isSuccessful();          // all channels succeeded
$result->hasPartialSuccess();     // some succeeded
$result->getSuccessfulChannels();
$result->getFailedChannels();
$result->getErrors();             // ['slack' => '...']
$result->toArray();
```

### Service API

| Method | |
|--------|---|
| `send(NotificationMessage\|string $message, array $data = [], string\|array\|null $channels = null, string $level = 'info'): NotificationResult` | Send to the chosen (or default) channels. |
| `broadcast(NotificationMessage\|string $message, array $data = [], string $level = 'info'): NotificationResult` | Send to all default channels. |
| `dispatchNow(NotificationMessage $message, array $channelNames): NotificationResult` | Synchronous send (used by the queue worker). |
| `registerChannel` / `unregisterChannel` / `getChannel` / `getChannels` | Manage channel instances. |
| `setDefaultChannels(array $channels)` | Set the default channel list. |

## Messages

`NotificationMessage` is a readonly DTO:

```php
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;

$message = new NotificationMessage(
    body: 'Your export is ready',
    subject: 'Export complete',
    to: 'ops@example.com',
    level: 'info',
    options: ['icon' => ':white_check_mark:'],
);

Notifications::send($message, channels: ['email', 'slack']);
```

Also `NotificationMessage::make($body, $data, $level)` and
`NotificationMessage::fromArray($payload)`; instance helpers `option()`,
`withOptions()`, `toData()`, `toArray()`.

## Queueing

Set `laranail.toolkit.notifications.queueable = true` (env
`NOTIFICATIONS_QUEUEABLE`) to push sends onto a queue. The dispatcher enqueues a
`SendQueuedNotification` job carrying a **JSON-safe scalar payload** (no closures,
mailer, or HTTP clients are serialized); the worker re-resolves a fresh
`NotificationService` and calls `dispatchNow()`. Configure
`queue_connection` and `queue_name` (default `notifications`).

## Configuration

Per-channel config lives under `laranail.toolkit.notifications.channels` —
e.g. `slack.webhook_url`, `discord.webhook_url`, `email.from`/`to`,
`sms.api_url`/`api_key`, `push.api_key`/`app_id`, `webhook.url`/`method`,
`database.table`, `cache.ttl`, `file.path`. See `config/notifications.php`.

## Security

- **Allow-listed channels** — the service only instantiates channel classes from
  the `NotificationChannel` enum; a class name from config can never be `new`-ed.
- **SSRF guard** — outbound-HTTP channels validate every target URL via
  `GuardsOutboundUrls`: only `http`/`https` schemes are allowed, and requests to
  loopback, private, link-local, reserved, unique-local, IPv4-mapped, and
  cloud-metadata hosts (e.g. `metadata.google.internal`, `169.254.169.254`,
  `::1`, `10.0.0.0/8`) are rejected. The check is deterministic and does no DNS
  resolution.
- **Fail soft** — channels return a boolean and never throw or log secrets;
  error summaries are caller-safe (no tokens/URLs leaked).

[← Docs index](../../README.md#documentation)
