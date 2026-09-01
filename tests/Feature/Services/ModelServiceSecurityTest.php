<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Services\ModelService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class ModelServiceSecurityTest extends TestCase
{
    private ModelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(ModelService::class);
    }

    public function test_unknown_table_is_rejected_not_interpolated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown table [users"; DROP TABLE users; --]');

        $this->service->concatName('users"; DROP TABLE users; --');
    }

    public function test_unknown_column_is_rejected(): void
    {
        Schema::create('partial_people', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            // intentionally no last_name column
        });

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Unknown column [partial_people.last_name]');

            $this->service->concatName('partial_people');
        } finally {
            Schema::dropIfExists('partial_people');
        }
    }
}
