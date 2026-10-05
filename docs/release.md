# Release process

`laranail/toolkit` is released **tag-driven**: pushing a `vX.Y.Z` tag triggers the release workflow, which publishes the GitHub Release with the tagged version's `CHANGELOG.md` block as its body.

## Versioning & stability

Pre-1.0, the package follows SemVer's 0.x convention: breaking changes bump the **minor** (`0.X.0`), fixes and additive features bump the patch. The PHP floor (`^8.4.1`) and the `laranail/console` constraint live in `composer.json` — a breaking bump in that upstream is itself a breaking change here. (The toolkit is self-contained and no longer depends on `laranail/package-tools`.)

**What the version contract covers:** the documented module surface (`Modules\*` public classes and facades) and the shipped Artisan commands. Anything marked `@internal` and module internals' constructor signatures are excluded.

## Cutting a release

Everything reaches `main` through a pull request, the release included.

1. Land the changes on `main` by pull request, with every required check green. Each one adds its
   entry under `## [Unreleased]` in `CHANGELOG.md` (Keep a Changelog).
2. Branch `release/v0.X.Y` from `main` and make one commit, `Release 0.X.Y`, that renames
   `## [Unreleased]` to `## [0.X.Y] - <date>`.
3. Open a pull request for it, wait for the checks, and merge it with a merge commit.
4. Tag the merge commit and push the tag:

   ```bash
   git switch main && git pull --ff-only
   git tag v0.X.Y && git push origin v0.X.Y
   ```

The tag starts `.github/workflows/release.yml`, which checks that `extra.branch-alias` is on the
tag's line, refuses a tag while `[Unreleased]` still has entries, publishes the GitHub Release with
the version's `CHANGELOG.md` section as its body (never a bare stub) and attaches a CycloneDX SBOM.
To publish or refresh the release for an existing tag, run the workflow by hand with that tag.

Never move a published tag: consumers who already resolved it would receive different code under
the same name. Cut the next patch instead.

The repo is GitHub-only (not on Packagist); consumers resolve tags via a `vcs` repository entry, so the tag IS the release channel.

---

[← Docs index](../README.md#documentation)
