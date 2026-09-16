<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Archiver;

use ZipArchive;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Zip;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiveException;

/**
 * What the bomb guard actually rests on.
 *
 * The guard sums `statIndex()['size']` — the archive's own declaration of its
 * uncompressed size. That looks unsound: the attacker writes that field. An
 * earlier pass reasoned exactly that way and set out to replace `extractTo()`
 * with hand-rolled streaming so the ceiling could be measured against bytes
 * genuinely written.
 *
 * The reasoning was wrong, and `an_understated_size_truncates_rather_than_expanding`
 * is why: libzip **truncates extraction to the declared size**, so understating
 * it produces a 1-byte file rather than a bomb. The lie constrains the attacker.
 * These tests pin that behaviour, because the guard's correctness depends on it
 * and nothing else in the codebase records it.
 */
final class ZipBombGuardTest extends TestCase
{
    private const int MODE_SHIFT = 16;

    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/laranail-zipbomb-' . bin2hex(random_bytes(6));
        mkdir($this->sandbox, 0o755, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->sandbox));

        parent::tearDown();
    }

    #[Test]
    public function an_understated_size_truncates_rather_than_expanding(): void
    {
        // The load-bearing fact. 64 KB of real payload behind a declared size
        // of 1 byte extracts to *one byte* — libzip honours the declaration.
        // If this ever changes, summing declared sizes stops being a guard and
        // this test is where that shows up.
        $path = $this->sandbox . '/lying.zip';
        $this->lyingArchive($path, 65_536);

        (new Zip)->setLimits(maxEntries: 100, maxTotalBytes: 1_048_576)
            ->extract($path, $this->sandbox . '/out');

        self::assertSame(
            1,
            filesize($this->sandbox . '/out/payload.bin'),
            'libzip no longer truncates to the declared size — the bomb guard needs rewriting.',
        );
    }

    #[Test]
    public function an_honestly_declared_oversize_archive_is_refused(): void
    {
        // The real zip bomb: a small compressed payload with a large, honest
        // uncompressed size. This is what summing the declarations catches.
        $path = $this->sandbox . '/big.zip';
        $this->honestArchive($path, 32_768);

        $extractor = (new Zip)->setLimits(maxEntries: 100, maxTotalBytes: 4_096);

        $this->expectException(ArchiveException::class);

        $extractor->extract($path, $this->sandbox . '/out');
    }

    #[Test]
    public function nothing_is_written_when_the_ceiling_is_exceeded(): void
    {
        // Fail-closed: every entry is validated before a single byte lands.
        $path = $this->sandbox . '/big.zip';
        $this->honestArchive($path, 32_768);

        $refused = false;

        try {
            (new Zip)->setLimits(maxEntries: 100, maxTotalBytes: 4_096)
                ->extract($path, $this->sandbox . '/out');
        } catch (ArchiveException) {
            $refused = true;
        }

        // Both halves matter. Without the first, an extractor that quietly
        // succeeded and wrote nothing would pass this test just as happily as
        // one that refused — and "nothing was written" is not the claim; "it
        // refused, and therefore nothing was written" is.
        self::assertTrue($refused, 'The ceiling was exceeded but no ArchiveException was thrown.');
        self::assertFileDoesNotExist($this->sandbox . '/out/payload.bin');
    }

    #[Test]
    public function an_archive_inside_the_ceiling_extracts_normally(): void
    {
        $path = $this->sandbox . '/ok.zip';
        $this->honestArchive($path, 512);

        (new Zip)->setLimits(maxEntries: 100, maxTotalBytes: 1_048_576)
            ->extract($path, $this->sandbox . '/out');

        self::assertSame(512, filesize($this->sandbox . '/out/payload.bin'));
    }

    #[Test]
    public function a_symlink_entry_is_refused(): void
    {
        // P0-5, and the one that was genuinely missing: the tar path checks
        // isLink(), the ZIP path checked names only, and three docblocks
        // claimed symlink safety for both.
        $path = $this->sandbox . '/link.zip';
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('evil', '/etc/passwd');
        $archive->setExternalAttributesName('evil', ZipArchive::OPSYS_UNIX, 0o120777 << self::MODE_SHIFT);
        $archive->close();

        $this->expectException(ArchiveException::class);

        (new Zip)->extract($path, $this->sandbox . '/out');
    }

    /**
     * A ZIP whose headers understate the payload behind them.
     *
     * Hand-built, because `ZipArchive` computes honest sizes and an honest
     * archive cannot demonstrate the question. Both size fields say 1; the
     * stored payload is `$realBytes` long.
     */
    private function lyingArchive(string $path, int $realBytes): void
    {
        $name = 'payload.bin';
        $data = str_repeat('A', $realBytes);
        $crc = crc32($data);

        $local = "PK\x03\x04"
            . pack('v', 10) . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
            . pack('V', $crc) . pack('V', 1) . pack('V', 1)
            . pack('v', strlen($name)) . pack('v', 0)
            . $name . $data;

        $central = "PK\x01\x02"
            . pack('v', 10) . pack('v', 10) . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
            . pack('V', $crc) . pack('V', 1) . pack('V', 1)
            . pack('v', strlen($name)) . pack('v', 0) . pack('v', 0)
            . pack('v', 0) . pack('v', 0) . pack('V', 0) . pack('V', 0)
            . $name;

        $end = "PK\x05\x06" . pack('v', 0) . pack('v', 0) . pack('v', 1) . pack('v', 1)
            . pack('V', strlen($central)) . pack('V', strlen($local)) . pack('v', 0);

        file_put_contents($path, $local . $central . $end);
    }

    private function honestArchive(string $path, int $bytes): void
    {
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('payload.bin', str_repeat('A', $bytes));
        $archive->close();
    }
}
