<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Laravel\Macros;

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
                    return (new Collection((array) $value))->recursive();
                }

                return $value;
            });
        });

        Collection::macro('collectBy', function (callable $callback): Collection {
            /** @var Collection<array-key, mixed> $this */
            return $this->groupBy($callback)->map(static fn (mixed $group): Collection => new Collection($group));
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
                    return (new Collection($value))->filterRecursive($callback);
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

            return (new Collection($start))->merge([$key => $value])->merge($end);
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

            return (new Collection($start))->merge([$key => $value])->merge($end);
        });
    }
}
