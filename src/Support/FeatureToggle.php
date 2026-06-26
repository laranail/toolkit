<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class FeatureToggle
{
    /**
     * Check if a feature is enabled.
     */
    public static function isEnabled(string $feature): bool
    {
        self::ensureConfigFileExists();

        // Check if the feature is explicitly enabled or disabled in the configuration
        $isEnabledInConfig = Config::get("laranail-toolkit-feature-toggles.{$feature}", false);

        // Check if there's a per-user or per-environment override
        $overrideKey = "laranail-toolkit-feature-toggles.{$feature}." . self::getOverrideKey();
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

    /**
     * Ensure the published config file exists, if not, create it from the
     * package default. The published file lives at the namespaced path so it
     * cannot collide with an app- or other-package "feature-toggles" config.
     */
    private static function ensureConfigFileExists(): void
    {
        $configPath = config_path('laranail-toolkit-feature-toggles.php');

        // Create the configuration file if it doesn't exist
        if (!File::exists($configPath)) {
            $sourcePath = __DIR__ . '/../../config/feature-toggles.php';
            if (File::exists($sourcePath)) {
                File::copy($sourcePath, $configPath);
            }
        }
    }
}
