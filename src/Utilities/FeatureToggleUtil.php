<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Utilities;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class FeatureToggleUtil
{
    /**
     * Check if a feature is enabled.
     */
    public static function isEnabled(string $feature): bool
    {
        self::ensureConfigFileExists();

        // Check if the feature is explicitly enabled or disabled in the configuration
        $isEnabledInConfig = Config::get("feature-toggles.{$feature}", false);

        // Check if there's a per-user or per-environment override
        $overrideKey = "feature-toggles.{$feature}." . self::getOverrideKey();
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

        return $user ? 'user.' . $user->id : 'environment.' . app()->environment();
    }

    /**
     * Ensure the feature-toggles.php configuration file exists, if not, create it.
     */
    private static function ensureConfigFileExists()
    {
        $configPath = config_path('feature-toggles.php');

        // Create the configuration file if it doesn't exist
        if (!File::exists($configPath)) {
            $sourcePath = __DIR__ . '/../../config/feature-toggles.php';
            if (File::exists($sourcePath)) {
                File::copy($sourcePath, $configPath);
            }
        }
    }
}
