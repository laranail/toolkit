<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers;

use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithArrays;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithConsole;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithDatabase;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithDates;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithFiles;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithGeo;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithStrings;
use Simtabi\Laranail\Toolkit\Helpers\Concerns\InteractsWithSystem;

/**
 * One **stateless** static helper facade, composed of per-domain traits.
 *
 * Every method is `static` — call them directly (`Helper::uuid()`), no container
 * resolution or injection required. This holds the genuinely-reusable
 * functionality rescued from the legacy monolith's grab-bag helpers, regrouped
 * by concern into the {@see Concerns}
 * `InteractsWith*` traits:
 *
 * - {@see InteractsWithArrays}   — arrayTrim, arrayFlatten, arrayToDotNotation
 * - {@see InteractsWithStrings}  — strBetween, strSlugify, ucWords, usernameFromEmail,
 *                                  emailFromUsername, nameToUsernames, uuid, escapeHtml,
 *                                  classBasename, randomIntExcept, faker
 * - {@see InteractsWithDates}    — carbonParse, carbonHumanDiff
 * - {@see InteractsWithSystem}   — parseMemoryLimit, memoryLimit, memoryUsage, phpVersion,
 *                                  isPhpVersionSupported, isCli, isHttps, isSslInstalled,
 *                                  composer, composerPackageVersion, systemInfo, serverEnv
 * - {@see InteractsWithFiles}    — formatFileSize, extension, filenameWithoutExtension,
 *                                  isImage, sanitizeFilename, exists, size, lastModified,
 *                                  hasAllowedExtension, fileInfo
 * - {@see InteractsWithDatabase} — canConnect, canConnectWith, tableExists, columnExists,
 *                                  connectionNames
 * - {@see InteractsWithGeo}      — distanceBetween
 * - {@see InteractsWithConsole}  — write
 *
 * These are deliberately **not** fronted by the `Toolkit` facade — they are pure
 * static utilities, so call them by class directly.
 */
final class Helper
{
    use InteractsWithArrays;
    use InteractsWithConsole;
    use InteractsWithDatabase;
    use InteractsWithDates;
    use InteractsWithFiles;
    use InteractsWithGeo;
    use InteractsWithStrings;
    use InteractsWithSystem;
}
