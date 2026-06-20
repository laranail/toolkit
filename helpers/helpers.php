<?php

declare(strict_types=1);

/**
 * Laranail Helper Functions
 *
 * IMPORTANT NOTICE:
 * ================
 * The application-specific helper functions (pathToPlugin, pathToPlatform,
 * pathToCore, pathToPackage) have been REMOVED in v2.0.
 *
 * These helpers assumed a specific directory structure and were not suitable
 * for a general-purpose Laravel package.
 *
 * MIGRATION:
 * ==========
 * If your application used these helpers, please add them to your own
 * app/helpers.php file or create a dedicated helper file for your project.
 *
 * Example migration:
 *
 * In your app/Providers/AppServiceProvider.php boot() method:
 *
 *     require_once app_path('helpers.php');
 *
 * In your app/helpers.php:
 *
 *     function pathToPlugin(?string $path = null): string {
 *         return base_path('platform/plugins/' . $path);
 *     }
 *
 * For more information, see: UPGRADE.md
 */

// Future general-purpose helper functions will be added here
// For now, this file is kept for backward compatibility but contains no functions
