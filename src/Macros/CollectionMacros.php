<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Registers the toolkit's general-purpose Collection macros.
 */
final class CollectionMacros extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCollectionMacros();
    }

    private function registerCollectionMacros(): void
    {
        Collection::macro('transpose', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            $array = $this->toArray();

            if ($array === []) {
                return new Collection([]);
            }

            return new Collection(array_map(null, ...array_values($array)));
        });

        Collection::macro('recursive', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->map(function (mixed $value): mixed {
                if (is_array($value) || is_object($value)) {
                    return new Collection((array) $value)->recursive();
                }

                return $value;
            });
        });

        Collection::macro('mapToKey', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            $result = [];

            foreach ($this as $key => $value) {
                $mappedValue = $callback($value, $key);
                $result[$mappedValue[0]] = $mappedValue[1];
            }

            return new Collection($result);
        });

        Collection::macro('filterRecursive', function (?callable $callback = null): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->map(function (mixed $value) use ($callback): mixed {
                if ($value instanceof Collection || is_array($value)) {
                    return new Collection($value)->filterRecursive($callback);
                }

                return $value;
            })->filter($callback);
        });

        Collection::macro('firstOrFail', function (?callable $callback = null, mixed $default = null): mixed {
            /** @var Collection<array-key, mixed> $this */
            $result = $this->first($callback, $default);

            if ($result === null) {
                throw new RuntimeException('No items found in collection.');
            }

            return $result;
        });

        Collection::macro('sumRecursive', function (mixed $key = null): mixed {
            /** @var Collection<array-key, mixed> $this */
            return $this->flatten()->sum($key);
        });

        Collection::macro('averageBy', function (callable $callback): mixed {
            /** @var Collection<array-key, mixed> $this */
            return $this->map($callback)->average();
        });

        Collection::macro('toCsv', function (string $delimiter = ',', string $enclosure = '"', string $escape = '\\'): array {
            /** @var Collection<array-key, mixed> $this */
            $rows = [];

            foreach ($this as $item) {
                $row = is_array($item) ? $item : (array) $item;
                $rows[] = implode($delimiter, array_map(
                    static fn (mixed $value): string => $enclosure
                        . str_replace($enclosure, $escape . $enclosure, (string) $value)
                        . $enclosure,
                    $row,
                ));
            }

            return $rows;
        });

        Collection::macro('prioritize', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->filter($callback)->merge($this->reject($callback));
        });

        Collection::macro('rotateLeft', function (int $count = 1): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                return new Collection([]);
            }

            $count %= $this->count();

            return $this->slice($count)->merge($this->take($count));
        });

        Collection::macro('rotateRight', function (int $count = 1): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                return new Collection([]);
            }

            return $this->rotateLeft($this->count() - ($count % $this->count()));
        });

        Collection::macro('toTree', function (string $parentKey = 'parent_id', string $childrenKey = 'children'): Collection {
            /** @var Collection<array-key, mixed> $this */
            $grouped = $this->groupBy($parentKey);

            $buildTree = function (mixed $parentId) use ($grouped, $childrenKey, &$buildTree): Collection {
                $children = $grouped->get($parentId);

                if (!$children instanceof Collection) {
                    return new Collection([]);
                }

                return $children->map(function (mixed $item) use ($childrenKey, $buildTree): mixed {
                    $item[$childrenKey] = $buildTree($item['id'] ?? null);

                    return $item;
                });
            };

            return $buildTree(null);
        });

        Collection::macro('insertAfter', function (mixed $key, mixed $value): Collection {
            /** @var Collection<array-key, mixed> $this */
            $index = $this->keys()->search($key);

            if (!is_int($index)) {
                return $this->put($key, $value);
            }

            $items = $this->toArray();
            $offset = $index + 1;
            $start = array_slice($items, 0, $offset, true);
            $end = array_slice($items, $offset, null, true);

            return new Collection($start)->merge([$key => $value])->merge($end);
        });

        Collection::macro('insertBefore', function (mixed $key, mixed $value): Collection {
            /** @var Collection<array-key, mixed> $this */
            $index = $this->keys()->search($key);

            if (!is_int($index)) {
                return $this->prepend($value, $key);
            }

            $items = $this->toArray();
            $start = array_slice($items, 0, $index, true);
            $end = array_slice($items, $index, null, true);

            return new Collection($start)->merge([$key => $value])->merge($end);
        });

        $this->registerNavigationMacros();
        $this->registerChunkingMacros();
        $this->registerReshapeMacros();
    }

    /**
     * Item-navigation and positional-insertion macros.
     */
    private function registerNavigationMacros(): void
    {
        // The previous item relative to $current (mirror of the native after()).
        // Returns null when $current is the first item or is not present.
        Collection::macro('before', function (mixed $current, bool $strict = false): mixed {
            /** @var Collection<array-key, mixed> $this */
            return $this->reverse()->after($current, $strict);
        });

        // Insert $item at a positional $index (optionally keyed). Returns a new
        // collection — unlike the legacy version it does not mutate in place.
        Collection::macro('insertAt', function (int $index, mixed $item, mixed $key = null): Collection {
            /** @var Collection<array-key, mixed> $this */
            $head = $this->slice(0, $index);
            $tail = $this->slice($index);

            $inserted = $key !== null ? new Collection([$key => $item]) : new Collection([$item]);

            return $head->merge($inserted)->merge($tail)->values();
        });

        // Rotate items by a (possibly negative) offset.
        Collection::macro('rotate', function (int $offset): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                return new Collection([]);
            }

            $count = $this->count();
            $offset %= $count;

            if ($offset < 0) {
                $offset += $count;
            }

            return $this->slice($offset)->merge($this->take($offset))->values();
        });

        // First item passing $callback, or push value($value) onto $instance
        // (defaulting to this collection) and return it.
        Collection::macro('firstOrPush', function (
            callable $callback,
            mixed $value,
            ?Collection $instance = null,
        ): mixed {
            /** @var Collection<array-key, mixed> $this */
            $found = $this->first($callback);

            if ($found !== null) {
                return $found;
            }

            $resolved = value($value);
            ($instance ?? $this)->push($resolved);

            return $resolved;
        });
    }

    /**
     * Consecutive-window and predicate-chunking macros.
     */
    private function registerChunkingMacros(): void
    {
        // Consecutive overlapping windows of $chunkSize items.
        Collection::macro('eachCons', function (int $chunkSize, bool $preserveKeys = false): Collection {
            /** @var Collection<array-key, mixed> $this */
            $result = new Collection([]);
            $limit = $this->count() - $chunkSize;

            for ($index = 0; $index <= $limit; $index++) {
                $window = $this->slice($index, $chunkSize);
                $result->push($preserveKeys ? $window : $window->values());
            }

            return $preserveKeys ? $result : $result->values();
        });

        // Split into chunks before each point where $callback's result changes.
        Collection::macro('sliceBefore', function (callable $callback, bool $preserveKeys = false): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                return new Collection([]);
            }

            $chunks = new Collection([]);
            $current = null;

            foreach ($this as $key => $item) {
                if ($current === null) {
                    $current = $preserveKeys ? new Collection([$key => $item]) : new Collection([$item]);

                    continue;
                }

                $previous = $current->last();

                if ($callback($item, $previous)) {
                    $chunks->push($current);
                    $current = $preserveKeys ? new Collection([$key => $item]) : new Collection([$item]);
                } elseif ($preserveKeys) {
                    $current->put($key, $item);
                } else {
                    $current->push($item);
                }
            }

            if ($current instanceof Collection) {
                $chunks->push($current);
            }

            return $chunks;
        });

        // Chunk while $callback's result over consecutive items stays the same.
        Collection::macro('chunkBy', function (callable $callback, bool $preserveKeys = false): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->sliceBefore(
                static fn (mixed $item, mixed $previous): bool => $callback($item) !== $callback($previous),
                $preserveKeys,
            );
        });

        // Group by an Eloquent model resolved from each item, returning rows of
        // [model, items].
        Collection::macro('groupByModel', function (
            callable|string $callback,
            mixed $modelKey = 0,
            mixed $itemsKey = 1,
        ): Collection {
            /** @var Collection<array-key, mixed> $this */
            $resolver = is_string($callback)
                ? static fn (mixed $item): mixed => data_get($item, $callback)
                : $callback;

            return $this
                ->groupBy(static fn (mixed $item): int|string => $resolver($item)->getKey())
                ->map(static fn (Collection $items): array => [
                    $modelKey => $resolver($items->first()),
                    $itemsKey => $items,
                ])
                ->values();
        });
    }

    /**
     * Key/value reshaping and conditional macros.
     */
    private function registerReshapeMacros(): void
    {
        // Sort by lowercased $value, key by $key, optionally prepend an empty option.
        Collection::macro('forSelectBox', function (string $key, string $value, bool $addEmpty = true): array {
            /** @var Collection<array-key, mixed> $this */
            $options = $this
                ->sortBy(static fn (mixed $item): string => mb_strtolower((string) data_get($item, $value)))
                ->mapWithKeys(static fn (mixed $item): array => [
                    data_get($item, $key) => data_get($item, $value),
                ])
                ->all();

            return $addEmpty ? ['' => ''] + $options : $options;
        });

        // Pull the given keys in order, substituting null for missing ones, and
        // drop the keys so list() destructuring works.
        Collection::macro('extract', function (mixed $keys): Collection {
            /** @var Collection<array-key, mixed> $this */
            $keys = is_array($keys) ? $keys : func_get_args();

            return new Collection(array_map(
                fn (mixed $key): mixed => data_get($this->all(), $key),
                $keys,
            ));
        });

        // Everything except the first item.
        Collection::macro('tail', function (bool $preserveKeys = false): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $preserveKeys ? $this->slice(1) : $this->slice(1)->values();
        });

        // To a collection of [key, value] pairs.
        Collection::macro('toPairs', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->map(static fn (mixed $value, mixed $key): array => [$key, $value])->values();
        });

        // From a collection of [key, value] pairs back to an associative collection.
        Collection::macro('fromPairs', function (): Collection {
            /** @var Collection<array-key, mixed> $this */
            $assoc = [];

            foreach ($this as $pair) {
                [$key, $value] = $pair;
                $assoc[$key] = $value;
            }

            return new Collection($assoc);
        });

        // Run $callback when the collection is empty, then return the collection.
        Collection::macro('ifEmpty', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            if ($this->isEmpty()) {
                $callback($this);
            }

            return $this;
        });
    }
}
