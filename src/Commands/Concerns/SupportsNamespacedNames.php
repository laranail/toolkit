<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Commands\Concerns;

use ReflectionProperty;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * Allows commands to use the laranail `::` namespace separator
 * (e.g. `laranail::toolkit.make-crud`).
 *
 * Symfony Console's private `Command::validateName()` rejects the empty
 * segment in `::` (its pattern is `^[^:]++(:[^:]++)*$`). These overrides
 * write the name and aliases directly to Symfony's private properties,
 * bypassing that validator. Dispatch still works because Symfony resolves
 * an exact command name before its `:`-splitting namespace lookup.
 */
trait SupportsNamespacedNames
{
    public function setName(string $name): static
    {
        $this->writeConsoleProperty('name', $name);

        return $this;
    }

    /**
     * @param iterable<int, string> $aliases
     */
    public function setAliases(iterable $aliases): static
    {
        $list = [];

        foreach ($aliases as $alias) {
            $list[] = $alias;
        }

        $this->writeConsoleProperty('aliases', $list);

        return $this;
    }

    private function writeConsoleProperty(string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty(SymfonyCommand::class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($this, $value);
    }
}
