<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects;

/**
 * Immutable, typed notification payload.
 *
 * Replaces the legacy pattern of threading loose `string $message, array $data`
 * pairs through every channel. Carries the body, an optional subject, an
 * optional recipient, a severity level, and a free-form options bag for
 * channel-specific extras (e.g. Slack attachments, Discord embeds).
 *
 * Built only through the named constructors so the value object can be rebuilt
 * from a queued, JSON-safe payload without losing typing.
 */
final readonly class NotificationMessage
{
    /**
     * @param string|array<int, string>|null $to
     * @param array<string, mixed>           $options
     */
    public function __construct(
        public string $body,
        public ?string $subject = null,
        public string|array|null $to = null,
        public string $level = 'info',
        public array $options = [],
    ) {}

    /**
     * Build a message from a plain body string plus the legacy "data" bag.
     *
     * Recognised keys (`subject`, `to`, `level`) are lifted onto typed
     * properties; everything else is preserved in {@see self::$options} so no
     * channel-specific data is lost.
     *
     * @param array<string, mixed> $data
     */
    public static function make(string $body, array $data = [], string $level = 'info'): self
    {
        $subject = isset($data['subject']) ? (string) $data['subject'] : null;

        /** @var string|array<int, string>|null $to */
        $to = $data['to'] ?? null;

        $resolvedLevel = isset($data['level']) ? (string) $data['level'] : $level;

        unset($data['subject'], $data['to'], $data['level']);

        return new self(
            body: $body,
            subject: $subject,
            to: $to,
            level: $resolvedLevel,
            options: $data,
        );
    }

    /**
     * Rebuild a message from its serialized array form.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var string|array<int, string>|null $to */
        $to = $payload['to'] ?? null;

        /** @var array<string, mixed> $options */
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];

        return new self(
            body: (string) ($payload['body'] ?? ''),
            subject: isset($payload['subject']) ? (string) $payload['subject'] : null,
            to: $to,
            level: (string) ($payload['level'] ?? 'info'),
            options: $options,
        );
    }

    /**
     * Read a channel-specific option, falling back to the given default.
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Return a copy with extra options merged in (e.g. the severity level).
     *
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): self
    {
        return new self(
            body: $this->body,
            subject: $this->subject,
            to: $this->to,
            level: $this->level,
            options: array_merge($this->options, $options),
        );
    }

    /**
     * The channel-facing "data" view: typed fields folded back into one array,
     * mirroring the legacy `$data` shape that channels historically read from.
     *
     * @return array<string, mixed>
     */
    public function toData(): array
    {
        $data = $this->options;
        $data['level'] = $this->level;

        if ($this->subject !== null) {
            $data['subject'] = $this->subject;
        }

        if ($this->to !== null) {
            $data['to'] = $this->to;
        }

        return $data;
    }

    /**
     * Fully serializable representation for queueing.
     *
     * @return array{body: string, subject: string|null, to: string|array<int, string>|null, level: string, options: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'body' => $this->body,
            'subject' => $this->subject,
            'to' => $this->to,
            'level' => $this->level,
            'options' => $this->options,
        ];
    }
}
