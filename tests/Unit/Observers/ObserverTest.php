<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Observers;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Observers\Observer;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Bare Eloquent model used as the observed subject.
 */
class ObservedModel extends Model
{
    protected $table = 'observed_models';

    public $timestamps = false;

    protected $guarded = [];
}

/**
 * Concrete observer that records which base hooks fired so the no-op base can
 * be exercised end to end.
 */
class RecordingObserver extends Observer
{
    /** @var list<string> */
    public array $calls = [];

    public function created(Model $model): void
    {
        $this->calls[] = 'created';
    }
}

#[Group('observers')]
class ObserverTest extends TestCase
{
    /**
     * Every lifecycle hook the base declares — used to drive the no-op coverage.
     *
     * @return list<string>
     */
    private function lifecycleHooks(): array
    {
        return [
            'retrieved', 'creating', 'created', 'updating', 'updated',
            'saving', 'saved', 'deleting', 'deleted', 'restoring',
            'restored', 'forceDeleted',
        ];
    }

    public function test_base_declares_every_conventional_lifecycle_hook(): void
    {
        $observer = new class() extends Observer {};

        foreach ($this->lifecycleHooks() as $hook) {
            $this->assertTrue(
                method_exists($observer, $hook),
                "Observer is missing the [{$hook}] hook.",
            );
        }
    }

    public function test_every_base_hook_is_a_no_op_returning_void(): void
    {
        $observer = new class() extends Observer {};
        $model = new ObservedModel();

        // Each base hook is a void no-op; calling it must neither throw nor
        // mutate the model.
        $observer->retrieved($model);
        $observer->creating($model);
        $observer->created($model);
        $observer->updating($model);
        $observer->updated($model);
        $observer->saving($model);
        $observer->saved($model);
        $observer->deleting($model);
        $observer->deleted($model);
        $observer->restoring($model);
        $observer->restored($model);
        $observer->forceDeleted($model);

        // Reaching here without a throw — and with the model untouched — proves
        // every base hook is an inert no-op.
        $this->assertSame([], $model->getAttributes());
    }

    public function test_a_subclass_may_override_only_the_hooks_it_cares_about(): void
    {
        $observer = new RecordingObserver();
        $model = new ObservedModel();

        // Overridden hook records; un-overridden hooks fall through to the base
        // no-op without error.
        $observer->created($model);
        $observer->creating($model);
        $observer->deleted($model);

        $this->assertSame(['created'], $observer->calls);
    }
}
