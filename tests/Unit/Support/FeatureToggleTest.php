<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Simtabi\Laranail\Toolkit\Support\FeatureToggle;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class FeatureToggleTest extends TestCase
{
    public function test_feature_is_enabled_when_configured_true()
    {
        Config::set('laranail.toolkit.feature-toggles.test_feature', true);

        $result = FeatureToggle::isEnabled('test_feature');

        $this->assertTrue($result);
    }

    public function test_feature_is_disabled_when_configured_false()
    {
        Config::set('laranail.toolkit.feature-toggles.test_feature', false);

        $result = FeatureToggle::isEnabled('test_feature');

        $this->assertFalse($result);
    }

    public function test_feature_is_disabled_by_default()
    {
        $result = FeatureToggle::isEnabled('non_existent_feature');

        $this->assertFalse($result);
    }

    public function test_user_override_takes_precedence()
    {
        // Set base feature to false
        Config::set('laranail.toolkit.feature-toggles.test_feature', false);

        // Mock authenticated user
        $user = new \stdClass();
        $user->id = 123;
        Auth::shouldReceive('user')->andReturn($user);

        // Set user override to true
        Config::set('laranail.toolkit.feature-toggles.test_feature.user.123', true);

        $result = FeatureToggle::isEnabled('test_feature');

        $this->assertTrue($result);
    }

    public function test_environment_override_takes_precedence()
    {
        // Set base feature to true
        Config::set('laranail.toolkit.feature-toggles.test_feature', true);

        // Mock no authenticated user
        Auth::shouldReceive('user')->andReturn(null);

        // Set environment override to false
        Config::set('laranail.toolkit.feature-toggles.test_feature.environment.testing', false);

        $result = FeatureToggle::isEnabled('test_feature');

        $this->assertFalse($result);
    }
}
