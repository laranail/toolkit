<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Support;

/**
 * Aggregate outcome of a notification dispatch across one or more channels.
 *
 * Maps each channel name to a boolean delivery result and collects any error
 * messages keyed by channel. Error strings are caller-safe summaries — channels
 * are responsible for never placing secrets (URLs, tokens) into them.
 */
final readonly class NotificationResult
{
    /**
     * @param array<string, bool>   $results Channel name => delivered?
     * @param array<string, string> $errors  Channel name => caller-safe error message.
     */
    public function __construct(
        private array $results,
        private array $errors = [],
    ) {}

    public function isSuccessful(): bool
    {
        return empty($this->errors) && !in_array(false, $this->results, true);
    }

    public function hasPartialSuccess(): bool
    {
        return !empty($this->errors) || in_array(false, $this->results, true);
    }

    /**
     * @return array<string, bool>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function getSuccessfulChannels(): array
    {
        return array_keys(array_filter($this->results, static fn (bool $result): bool => $result === true));
    }

    /**
     * @return array<int, string>
     */
    public function getFailedChannels(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(array_filter($this->results, static fn (bool $result): bool => $result === false)),
            array_keys($this->errors),
        )));
    }

    /**
     * @return array{successful: bool, results: array<string, bool>, errors: array<string, string>, channels: array{successful: array<int, string>, failed: array<int, string>}}
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->isSuccessful(),
            'results' => $this->results,
            'errors' => $this->errors,
            'channels' => [
                'successful' => $this->getSuccessfulChannels(),
                'failed' => $this->getFailedChannels(),
            ],
        ];
    }
}
