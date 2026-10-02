# Archiver module

Create and safely extract `tar`, `tar.gz`, and `zip` archives behind
`ArchiverServiceInterface`. Bound through a deferred provider (alias
`laranail.archiver`, facade `Archiver`). The zip extractor requires `ext-zip`.

```php
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Archiver;
```

## Extract

Pick the extractor automatically from the file extension:

```php
Archiver::extract(
    storage_path('app/release.zip'),
    storage_path('app/release'),
);
```

Or via the contract:

```php
app(ArchiverServiceInterface::class)->extract($pathToArchive, $pathToDirectory);
```

## Per-format access

```php
Archiver::zip();    // Zip service
Archiver::tar();    // Tar service
Archiver::tarGz();  // TarGz service
```

## Security: Zip-Slip hardened

Extraction is **fail-closed**. Before any bytes are written, every archive entry
is validated:

- **Path traversal / Zip-Slip** — each entry's destination is asserted to stay
  within the target directory; entries that would escape (e.g. `../../etc`) abort
  the whole extraction with an `ArchiveException`.
- **Symlinks** — symlinked entries are rejected.
- **Zip bombs** — total file count and uncompressed size are checked against
  limits before extraction proceeds. The defaults are **10,000 entries** and
  **1 GiB** uncompressed; adjust them on the `Extractor` with
  `setLimits(int $maxEntries, int $maxTotalBytes): static` before extracting.

- **Entries that lie about themselves** — the size limit is checked against the
  sizes the archive declares, so each entry is then copied with a bounded read:
  never more than its declared size is read, and its content must match the
  declared size and CRC-32. An entry that is shorter, longer or corrupt is
  refused with `ArchiveException`, whatever the installed zip library does with
  it.

A malformed or unreadable entry aborts the operation, and the files and
directories that extraction had already created are removed, so a refused
archive leaves nothing partially extracted. Always extract untrusted archives
into a dedicated, isolated directory.

[← Docs index](../../README.md#documentation)
