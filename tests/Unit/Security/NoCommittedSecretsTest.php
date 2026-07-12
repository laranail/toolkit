<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guard against credentials accidentally committed to the package source.
 * A defence-in-depth complement to the gitleaks CI scan.
 */
#[Group('security')]
class NoCommittedSecretsTest extends TestCase
{
    /**
     * High-signal provider key shapes (regex => label).
     *
     * @var array<string, string>
     */
    private const SECRET_PATTERNS = [
        '/sk-[A-Za-z0-9]{32,}/' => 'OpenAI-style secret key',
        '/AIza[0-9A-Za-z_\-]{35}/' => 'Google API key',
        '/xox[baprs]-[0-9A-Za-z\-]{10,}/' => 'Slack token',
        '/AKIA[0-9A-Z]{16}/' => 'AWS access key id',
        '/ghp_[0-9A-Za-z]{36}/' => 'GitHub personal access token',
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----/' => 'PEM private key',
    ];

    public function test_no_secret_shaped_strings_in_shipped_source(): void
    {
        $root = dirname(__DIR__, 3);
        $scanDirs = ['src', 'config', 'database', 'resources', 'stubs', 'routes'];
        $offenders = [];

        foreach ($scanDirs as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                /** @var \SplFileInfo $file */
                $contents = (string) file_get_contents($file->getPathname());

                foreach (self::SECRET_PATTERNS as $pattern => $label) {
                    if (preg_match($pattern, $contents) === 1) {
                        $offenders[] = sprintf('%s in %s', $label, $file->getPathname());
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Secret-shaped strings found in shipped source:\n" . implode("\n", $offenders),
        );
    }
}
