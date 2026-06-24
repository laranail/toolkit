<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use Simtabi\Laranail\Toolkit\Helpers\ConsoleHelper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class ConsoleHelperTest extends TestCase
{
    public function test_write_wraps_each_line_in_the_style_tag(): void
    {
        $output = new BufferedOutput();

        ConsoleHelper::write($output, 'info', 'first', 'second');

        $this->assertSame("first\nsecond\n", $output->fetch());
    }

    public function test_write_with_no_lines_emits_nothing(): void
    {
        $output = new BufferedOutput();

        ConsoleHelper::write($output, 'comment');

        $this->assertSame('', $output->fetch());
    }
}
