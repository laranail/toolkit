<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Commands;

use Illuminate\Console\Command as IlluminateCommand;
use Simtabi\Laranail\Toolkit\Commands\Concerns\SupportsNamespacedNames;

/**
 * Base command for every Artisan command shipped by the toolkit.
 *
 * Commands name themselves `laranail::toolkit.<command>` (the org-wide shape)
 * and may expose convenience aliases via {@see $commandAliases}.
 */
abstract class Command extends IlluminateCommand
{
    use SupportsNamespacedNames;

    /**
     * Convenience aliases (e.g. the bare `make:crud`) applied after the
     * fluent signature is parsed.
     *
     * @var list<string>
     */
    protected array $commandAliases = [];

    public function __construct()
    {
        parent::__construct();

        if ($this->commandAliases !== []) {
            $this->setAliases($this->commandAliases);
        }
    }
}
