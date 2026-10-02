<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Archiver;

use ZipArchive;
use SplFileInfo;
use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Zip;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiveException;
use Simtabi\Laranail\Toolkit\Modules\Archiver\BoundedEntryWriter;

/**
 * What the bomb guard actually rests on.
 *
 * The guard sums `statIndex()['size']` — the archive's own declaration of its
 * uncompressed size, which the attacker writes. Summing it refuses the honest
 * bomb before a byte lands; what keeps a *dishonest* declaration from expanding
 * past the sum is that no entry may produce more than it declared.
 *
 * That used to be a property of the runtime. libzip truncates an understated
 * entry to its declared size, and until PHP 8.4.25 / 8.5.10 `extractTo()` reported
 * that as success — so a 64 KB payload declared as 1 byte extracted to a 1-byte
 * file, and this class pinned exactly that. PHP 8.4.26 / 8.5.11 (php-src GH-23240)
 * made the same truncation fail its CRC: `extractTo()` warns, returns false and
 * leaves the truncated file behind. Same bound, different behaviour, and a guard
 * whose correctness depended on which one was installed.
 *
 * The guard now counts for itself (`BoundedEntryWriter`), so these tests assert
 * the security property rather than a library's incidental behaviour: an entry
 * that does not match its declaration is refused with `ArchiveException`, never
 * more than the declared size reaches disk, and nothing is left behind. The
 * writer tests drive each library behaviour directly — truncating, over-reading,
 * and aborting mid-stream — so all three paths run on every runtime, not only
 * the one CI happens to have.
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
    public function an_understated_size_is_refused_and_leaves_nothing_behind(): void
    {
        // 64 KB of real payload behind a declared size of 1 byte. Whatever the
        // zip library does with that — truncate, read on, or abort — the guard
        // refuses it, and no byte of it is left in the destination.
        $path = $this->sandbox . '/lying.zip';
        $this->lyingArchive($path, 65_536);

        $refused = false;

        try {
            (new Zip)->setLimits(maxEntries: 100, maxTotalBytes: 1_048_576)
                ->extract($path, $this->sandbox . '/out');
        } catch (ArchiveException) {
            $refused = true;
        }

        self::assertTrue($refused, 'An entry larger than its declared size was extracted without complaint.');
        self::assertSame([], $this->filesUnder($this->sandbox . '/out'));
    }

    #[Test]
    public function a_checksum_mismatch_at_the_declared_size_is_refused(): void
    {
        // Size honest, content not: the guard checks the CRC itself instead of
        // relying on the library to notice.
        $path = $this->sandbox . '/corrupt.zip';
        $this->rawArchive($path, [['payload.bin', 'AAAA', 4, crc32('BBBB')]]);

        $this->expectException(ArchiveException::class);

        (new Zip)->extract($path, $this->sandbox . '/out');
    }

    #[Test]
    public function a_refused_entry_rolls_back_the_entries_already_extracted(): void
    {
        // Fail-closed covers the second half of the archive too: a good entry
        // extracted before a bad one is removed, along with the directory it made.
        $path = $this->sandbox . '/mixed.zip';
        $this->rawArchive($path, [
            ['nested/good.txt', 'good', 4, crc32('good')],
            ['bad.bin', str_repeat('A', 4_096), 1, crc32(str_repeat('A', 4_096))],
        ]);

        $refused = false;

        try {
            (new Zip)->extract($path, $this->sandbox . '/out');
        } catch (ArchiveException) {
            $refused = true;
        }

        self::assertTrue($refused);
        self::assertSame([], $this->filesUnder($this->sandbox . '/out'));
        self::assertDirectoryDoesNotExist($this->sandbox . '/out/nested');
    }

    #[Test]
    public function an_existing_file_is_kept_when_its_replacement_is_refused(): void
    {
        // Rollback removes what this call created, never what was already there.
        mkdir($this->sandbox . '/out');
        file_put_contents($this->sandbox . '/out/payload.bin', 'original');

        $path = $this->sandbox . '/lying.zip';
        $this->lyingArchive($path, 4_096);

        $refused = false;

        try {
            (new Zip)->extract($path, $this->sandbox . '/out');
        } catch (ArchiveException) {
            $refused = true;
        }

        self::assertTrue($refused, 'The lying archive was extracted.');

        self::assertSame('original', file_get_contents($this->sandbox . '/out/payload.bin'));
        self::assertSame(['payload.bin'], $this->filesUnder($this->sandbox . '/out'));
    }

    #[Test]
    public function directory_entries_and_modification_times_are_kept(): void
    {
        $path = $this->sandbox . '/tree.zip';
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addEmptyDir('empty');
        $archive->addFromString('a/b/c.txt', 'deep');
        $archive->addFromString('zero.txt', '');
        $archive->setMtimeName('a/b/c.txt', 1_700_000_000);
        $archive->close();

        (new Zip)->extract($path, $this->sandbox . '/out');

        self::assertDirectoryExists($this->sandbox . '/out/empty');
        self::assertSame('deep', file_get_contents($this->sandbox . '/out/a/b/c.txt'));
        self::assertSame('', file_get_contents($this->sandbox . '/out/zero.txt'));
        self::assertSame(1_700_000_000, filemtime($this->sandbox . '/out/a/b/c.txt'));
    }

    #[Test]
    public function the_writer_refuses_a_library_that_truncates_to_the_declared_size(): void
    {
        // libzip's behaviour: one byte arrives, the checksum is for 64 KB.
        $declared = str_repeat('A', 65_536);

        $this->assertWriterRefuses($this->memoryStream('A'), 1, crc32($declared));
    }

    #[Test]
    public function the_writer_never_writes_past_the_declared_size_of_a_library_that_reads_on(): void
    {
        // A library that ignores the declaration and hands over everything. The
        // checksum even matches the full payload; the size still decides.
        $payload = str_repeat('A', 65_536);
        $source = $this->memoryStream($payload);

        $this->assertWriterRefuses($source, 1, crc32($payload));

        // The bound is on what is read, not only on what survives: the declared
        // byte plus the one-byte probe, never the 64 KB behind them.
        self::assertSame(2, ftell($source));
    }

    #[Test]
    public function the_writer_refuses_a_library_that_aborts_mid_stream(): void
    {
        // PHP 8.4.26+ / 8.5.11+: the read itself fails (a CRC error surfaced
        // by libzip), after some bytes have already been handed over.
        $this->assertWriterRefuses($this->failingStream(failAfter: 1), 8, crc32(str_repeat('A', 8)));
    }

    #[Test]
    public function the_writer_refuses_a_library_that_aborts_at_end_of_stream(): void
    {
        // The whole declared content arrives, and the error only surfaces on
        // the read that would detect end of stream — where libzip checks CRCs.
        $this->assertWriterRefuses($this->failingStream(failAfter: 1), 4, crc32('AAAA'));
    }

    #[Test]
    public function the_writer_writes_content_that_matches_its_declaration(): void
    {
        $target = $this->sandbox . '/ok.bin';
        $payload = str_repeat('Z', 200_000);

        $written = (new BoundedEntryWriter)->write(
            $this->memoryStream($payload),
            $target,
            'ok.bin',
            strlen($payload),
            crc32($payload),
        );

        self::assertSame(200_000, $written);
        self::assertSame($payload, file_get_contents($target));
        self::assertSame(['ok.bin'], $this->filesUnder($this->sandbox));
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
     * A ZIP whose headers understate the payload behind them: both size fields
     * say 1, the stored payload is `$realBytes` long, the CRC is the payload's.
     */
    private function lyingArchive(string $path, int $realBytes): void
    {
        $data = str_repeat('A', $realBytes);

        $this->rawArchive($path, [['payload.bin', $data, 1, crc32($data)]]);
    }

    /**
     * Hand-built, stored (uncompressed) entries, because `ZipArchive` computes
     * honest sizes and checksums and an honest archive cannot ask the question.
     *
     * @param list<array{0: string, 1: string, 2: int, 3: int}> $entries name, stored data, declared size, declared CRC
     */
    private function rawArchive(string $path, array $entries): void
    {
        $locals = '';
        $central = '';

        foreach ($entries as [$name, $data, $size, $crc]) {
            $offset = strlen($locals);

            $locals .= "PK\x03\x04"
                . pack('v', 10) . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
                . pack('V', $crc) . pack('V', $size) . pack('V', $size)
                . pack('v', strlen($name)) . pack('v', 0)
                . $name . $data;

            $central .= "PK\x01\x02"
                . pack('v', 10) . pack('v', 10) . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
                . pack('V', $crc) . pack('V', $size) . pack('V', $size)
                . pack('v', strlen($name)) . pack('v', 0) . pack('v', 0)
                . pack('v', 0) . pack('v', 0) . pack('V', 0) . pack('V', $offset)
                . $name;
        }

        $count = count($entries);
        $end = "PK\x05\x06" . pack('v', 0) . pack('v', 0) . pack('v', $count) . pack('v', $count)
            . pack('V', strlen($central)) . pack('V', strlen($locals)) . pack('v', 0);

        file_put_contents($path, $locals . $central . $end);
    }

    /**
     * @param resource $source
     */
    private function assertWriterRefuses(mixed $source, int $declaredSize, int $declaredCrc): void
    {
        $target = $this->sandbox . '/entry.bin';
        $refused = false;

        try {
            (new BoundedEntryWriter)->write($source, $target, 'entry.bin', $declaredSize, $declaredCrc);
        } catch (ArchiveException) {
            $refused = true;
        }

        self::assertTrue($refused, 'The writer accepted content that does not match its declaration.');
        self::assertFileDoesNotExist($target);
        self::assertSame([], $this->filesUnder($this->sandbox), 'A temporary or partial file was left behind.');
    }

    /**
     * @return resource
     */
    private function memoryStream(string $content): mixed
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * A stream that yields four bytes per read, then fails the read after
     * `$failAfter` successful ones — how a library reports a CRC error.
     *
     * @return resource
     */
    private function failingStream(int $failAfter): mixed
    {
        if (! in_array('laranail-failing', stream_get_wrappers(), true)) {
            stream_wrapper_register('laranail-failing', FailingReadStream::class);
        }

        $stream = fopen('laranail-failing://' . $failAfter, 'rb');
        self::assertIsResource($stream);

        return $stream;
    }

    /**
     * @return list<string>
     */
    private function filesUnder(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && ! str_ends_with($file->getFilename(), '.zip')) {
                $files[] = substr($file->getPathname(), strlen($directory) + 1);
            }
        }

        sort($files);

        return $files;
    }

    private function honestArchive(string $path, int $bytes): void
    {
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('payload.bin', str_repeat('A', $bytes));
        $archive->close();
    }
}

/**
 * Stream wrapper standing in for a zip library that reports corruption by
 * failing a read. The host part of the URL is how many reads succeed first.
 */
final class FailingReadStream
{
    /** @var resource|null */
    public $context;

    private int $remaining = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->remaining = (int) parse_url($path, PHP_URL_HOST);

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if ($this->remaining-- <= 0) {
            return false;
        }

        return str_repeat('A', min(4, $count));
    }

    public function stream_eof(): bool
    {
        return false;
    }
}
