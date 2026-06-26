<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Support\Facades\Config;

class FeatureToggle
{
    /**
     * Check if a feature is enabled. Flags live under the namespaced config key
     * `laranail.toolkit.feature-toggles.*` (merged by the package + overridable
     * by publishing the config — no runtime file copy needed).
     */
    public static function isEnabled(string $feature): bool
    {
        $isEnabledInConfig = Config::get("laranail.toolkit.feature-toggles.{$feature}", false);

        // Check if there's a per-user or per-environment override
        $overrideKey = "laranail.toolkit.feature-toggles.{$feature}." . self::getOverrideKey();
        $isOverridden = Config::get($overrideKey, null);

        if ($isOverridden !== null) {
            return (bool) $isOverridden;
        }

        return (bool) $isEnabledInConfig;
    }

    /**
     * Get the override key based on user or environment.
     */
    private static function getOverrideKey(): string
    {
        $user = auth()->user();

        return $user !== null ? 'user.' . $user->id : 'environment.' . app()->environment();
    }
}
