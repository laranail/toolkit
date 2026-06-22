<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
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
        $this->assertSame('@neo', new FormattableRecord(['username' => 'Neo'])->formattedUsername());
        $this->assertFalse(new FormattableRecord()->formattedUsername());
    }

    public function test_excerpt_truncates_content(): void
    {
        $record = new FormattableRecord(['content' => str_repeat('a', 200)]);

        $this->assertSame(13, mb_strlen($record->excerpt(10)));
    }

    public function test_suggest_username_returns_first_available_candidate(): void
    {
        Schema::create('formattable_records', function ($table): void {
            $table->increments('id');
            $table->string('username')->nullable();
        });

        // Take the primary candidate so the suggester skips to the next one.
        FormattableRecord::query()->create(['username' => 'janedoe']);

        $suggestion = new FormattableRecord()->suggestUsername('Jane', 'Doe');

        // The taken primary candidate must be skipped, and the result must be free.
        $this->assertNotSame('janedoe', $suggestion);
        $this->assertNotSame('', $suggestion);
        $this->assertFalse(FormattableRecord::query()->where('username', $suggestion)->exists());

        Schema::drop('formattable_records');
    }
}
