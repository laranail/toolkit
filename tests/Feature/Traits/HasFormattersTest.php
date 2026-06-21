<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasFormatters;

class FormattableRecord extends Model
{
    use HasFormatters;

    protected $table = 'formattable_records';

    public $timestamps = false;

    protected $guarded = [];
}

#[Group('traits')]
class HasFormattersTest extends TestCase
{
    public function test_formatted_created_at_uses_custom_format(): void
    {
        $record = new FormattableRecord(['created_at' => '2024-01-18 13:45:00']);

        $this->assertSame('2024-01-18', $record->formattedCreatedAt('Y-m-d'));
    }

    public function test_formatted_created_at_is_null_without_value(): void
    {
        $record = new FormattableRecord();

        $this->assertNull($record->formattedCreatedAt());
    }

    public function test_formatted_full_name_capitalises_parts(): void
    {
        $record = new FormattableRecord(['first_name' => 'jane', 'last_name' => 'doe']);

        $this->assertSame('Jane Doe', $record->formattedFullName());
    }

    public function test_formatted_username_prefixes_at_or_returns_false(): void
    {
        $this->assertSame('@neo', (new FormattableRecord(['username' => 'Neo']))->formattedUsername());
        $this->assertFalse((new FormattableRecord())->formattedUsername());
    }

    public function test_excerpt_truncates_content(): void
    {
        $record = new FormattableRecord(['content' => str_repeat('a', 200)]);

        $this->assertSame(13, mb_strlen($record->excerpt(10)));
    }
}
