<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Support\Username;
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

    public function test_formatted_created_at_uses_the_default_format_when_none_given(): void
    {
        $record = new FormattableRecord(['created_at' => '2024-01-18 13:45:00']);

        // Falls back to defaultDateTimeFormat(): 'm/d/Y h:i:s a'.
        $this->assertSame('01/18/2024 01:45:00 pm', $record->formattedCreatedAt());
    }

    public function test_formatted_updated_at_formats_and_is_null_without_value(): void
    {
        $record = new FormattableRecord(['updated_at' => '2024-03-09 08:05:30']);

        $this->assertSame('2024-03-09 08:05', $record->formattedUpdatedAt('Y-m-d H:i'));
        $this->assertNull((new FormattableRecord())->formattedUpdatedAt());
    }

    public function test_formatted_timestamp_treats_empty_string_as_null(): void
    {
        $record = new FormattableRecord(['created_at' => '']);

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

    public function test_formatted_content_strips_html_and_truncates(): void
    {
        $record = new FormattableRecord(['content' => '<p>Hello <b>world</b> of content</p>']);

        $this->assertSame('Hello world of content', $record->formattedContent(['strip_html' => true]));
        $this->assertSame('Hello...', $record->formattedContent(['strip_html' => true, 'truncate' => 5]));
        $this->assertSame('<p>Hello <b>world</b> of content</p>', $record->formattedContent());
    }

    public function test_format_address_joins_non_empty_components(): void
    {
        $record = new FormattableRecord();

        $this->assertSame(
            '123 Main St, Apt 4, Springfield',
            $record->formatAddress(['123 Main St', '', 'Apt 4', null, 'Springfield']),
        );
    }

    public function test_format_address_line_omits_empty_second_line(): void
    {
        $record = new FormattableRecord();

        $this->assertSame('123 Main St', $record->formatAddressLine('123 Main St'));
        $this->assertSame('123 Main St, Apt 4', $record->formatAddressLine('123 Main St', 'Apt 4'));
    }

    public function test_format_city_state_zip(): void
    {
        $record = new FormattableRecord();

        $this->assertSame('Springfield, Illinois 62704', $record->formatCityStateZip('springfield', 'illinois', '62704'));
    }

    public function test_suggest_username_returns_first_available_candidate(): void
    {
        Schema::create('formattable_records', function ($table): void {
            $table->increments('id');
            $table->string('username')->nullable();
        });

        // Take the primary candidate so the suggester skips to the next one.
        FormattableRecord::query()->create(['username' => 'janedoe']);

        $suggestion = (new FormattableRecord())->suggestUsername('Jane', 'Doe');

        // The taken primary candidate must be skipped, and the result must be free.
        $this->assertNotSame('janedoe', $suggestion);
        $this->assertNotSame('', $suggestion);
        $this->assertFalse(FormattableRecord::query()->where('username', $suggestion)->exists());

        Schema::drop('formattable_records');
    }

    public function test_suggest_username_falls_back_to_the_generator_when_every_candidate_is_taken(): void
    {
        $record = new ExhaustedUsernameRecord();

        // Establish how many deterministic/padded candidates the loop will probe;
        // the fixture denies all of them so suggestUsername() must reach the
        // bounded generate() fallback.
        $candidateCount = count(Username::fromName('Jane', 'Doe')->candidates());

        $suggestion = $record->suggestUsername('Jane', 'Doe');

        $this->assertNotSame('', $suggestion);
        $this->assertStringStartsWith('janedoe', $suggestion);
        // A random suffix was appended by the generator, so the handle is longer
        // than the bare 'janedoe' base — proving the fallback path ran.
        $this->assertGreaterThan(mb_strlen('janedoe'), mb_strlen($suggestion));
        // The candidate loop was fully exhausted before the generator kicked in.
        $this->assertGreaterThan($candidateCount, $record->availabilityCalls);
    }
}

/**
 * Host whose availability check denies enough early probes to exhaust the
 * candidate loop, forcing {@see HasFormatters::suggestUsername()} into its
 * bounded generator fallback.
 */
class ExhaustedUsernameRecord extends Model
{
    use HasFormatters;

    public int $availabilityCalls = 0;

    protected $table = 'exhausted_username_records';

    public $timestamps = false;

    protected $guarded = [];

    protected function usernameIsAvailable(string $username, string $column = 'username'): bool
    {
        $this->availabilityCalls++;

        // Deny the first 15 probes (the candidate loop is at most 10), so the
        // generator's random-suffixed retries are the first accepted handle.
        return $this->availabilityCalls > 15;
    }
}
