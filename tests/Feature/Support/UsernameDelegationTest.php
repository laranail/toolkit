<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Support\Username;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasFormatters;

class Account extends Model
{
    use HasFormatters;

    protected $table = 'accounts';

    public $timestamps = false;

    protected $guarded = [];
}

#[Group('support')]
class UsernameDelegationTest extends TestCase
{
    public function test_helper_username_from_email_delegates_and_keeps_legacy_shape(): void
    {
        $this->assertSame('john.doe', Helper::usernameFromEmail('john.doe@example.com'));
        $this->assertSame('user_123abc', Helper::usernameFromEmail('123abc@example.com'));
        $this->assertSame('user', Helper::usernameFromEmail('!!!@example.com'));
    }

    public function test_helper_name_to_usernames_delegates_to_candidates(): void
    {
        $candidates = Helper::nameToUsernames('Jane', 'Doe');

        $this->assertContains('janedoe', $candidates);
        $this->assertContains('jane.doe', $candidates);
        $this->assertContains('jdoe', $candidates);
    }

    public function test_helper_generate_username_delegates_to_random(): void
    {
        $this->assertMatchesRegularExpression('/^user[0-9]{4}$/', Helper::generateUsername());
        $this->assertMatchesRegularExpression('/^guest[0-9]{2}$/', Helper::generateUsername('guest', 2));
    }

    public function test_unique_checker_against_eloquent_backend(): void
    {
        Schema::create('accounts', function ($table): void {
            $table->increments('id');
            $table->string('username')->nullable();
        });

        Account::query()->create(['username' => 'janedoe']);

        $available = static fn (string $username): bool => !Account::query()
            ->where('username', $username)
            ->exists();

        $handle = Username::fromName('Jane', 'Doe')->unique($available)->generate();

        // The taken primary is skipped; the result must be genuinely free.
        $this->assertNotSame('janedoe', $handle);
        $this->assertFalse(Account::query()->where('username', $handle)->exists());

        Schema::drop('accounts');
    }

    public function test_suggest_username_uses_the_models_availability_checker(): void
    {
        Schema::create('accounts', function ($table): void {
            $table->increments('id');
            $table->string('username')->nullable();
        });

        Account::query()->create(['username' => 'janedoe']);

        $suggestion = (new Account())->suggestUsername('Jane', 'Doe');

        $this->assertNotSame('janedoe', $suggestion);
        $this->assertNotSame('', $suggestion);
        $this->assertFalse(Account::query()->where('username', $suggestion)->exists());

        Schema::drop('accounts');
    }
}
