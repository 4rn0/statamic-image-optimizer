# Changelog

## 2.1.0

### Changed
- Works together with [Statamic Image Editor](https://statamic.com/addons/4rn0/image-editor). Both addons keep the same original in `.meta/` and share one *Revert to original*. An image edited by the editor is optimized from its edited bytes instead of from the kept original, so an optimization can never undo an edit; the editor marks such images with `imageoptimizer.edited`. An edited image is optimized once; optimizing it again does nothing until the next edit, so lossy optimizers like cwebp cannot stack quality loss.
- Reverting regenerates the asset's meta (size, width, height), since an edit may have changed the dimensions, and the control panel shows the restored image right away: the listing, the asset editor preview and the thumbnails are refreshed after *Revert to original* and after the panel's buttons.
- The asset editor panel and the report treat an image with a kept original but no statistics (edited, not optimized yet) as not optimized; the panel keeps its *Revert* button.
- The original is copied right before the first smaller result replaces the file, instead of before every first run. Images that cannot get smaller (already optimized, AVIF, a missing or failing optimizer) no longer get a copy that doubles their storage; a later run that does shrink them still keeps the untouched file.
- Two optimizations, or an optimization and a revert, of the same asset now wait for each other (up to 30 seconds) instead of the second one being skipped.

### Fixed
- The compiled control panel assets were excluded from the package by an unanchored `build` line in `.gitignore`; only the Vite manifest shipped, so the control panel could not load the addon.
- The path of the optimizer binary is quoted in the shell command. A bundled binary under a path with a space (`Program Files`, a home folder with a space) silently failed.
- Asset IDs are encoded with Statamic's `utf8btoa`, so the asset editor panel and the utility's per-image loop work for filenames with characters outside Latin-1.
- A binary marked as broken is probed again after its permissions change, instead of staying red until the cache is cleared.
- A bulk run reports how many images failed; the failed jobs stay in the queue's failed jobs.
- The CSV export writes standard escaping; sizes below zero in the panel no longer render as `NaN`.

## 2.0.0

### Breaking
- Requires Statamic 6.5 or later and PHP 8.3.
- There is no configuration file anymore: every setting, the optimizer commands and the search paths included, lives in the control panel (`resources/addons/statamic-image-optimizer.yaml`). A published `config/statamic/imageoptimizer.php` is imported into it once by an update script and is not read afterwards.
- Images optimized before 2.0 have no kept original and cannot be reverted.

### Added
- Originals are kept: the first optimization stores a copy in the container's hidden `.meta/` folder, every later one starts from that copy. *Revert to original* and *Discard original* actions in the asset browser, a revert button in the asset editor, a *Keep originals* setting.
- Settings form on the utility page for optimizing uploads, optimizing Glide, keeping originals and logging, stored in `resources/addons/statamic-image-optimizer.yaml` (Statamic's addon settings storage). Gated by the existing utility permission. The optimizer commands and search paths are editable there too, with a status dot per binary, behind the new *Edit ImageOptimizer optimizers* permission (super users have it).
- WebP optimization with bundled cwebp 1.6.0 (Google's prebuilt binaries: Linux x86_64 and ARM64, macOS universal, Windows). Lossless and animated WebP files are skipped. The update script adds the cwebp entry to an imported 1.x config.
- Bundled binaries for Linux ARM64 (aarch64): AWS Graviton, Hetzner CAX and Ampere hosts no longer silently skip optimization. Both Linux sets are now built statically in Alpine by `bin/build-linux.sh`, so they run on any distribution without a matching glibc or zlib; see `bin/BUILD.md`.
- Savings report in the utility: per container and in total, with CSV export and a print-friendly page. Kept up to date: uploads, optimizations, reverts and deletions mark it stale and the utility rebuilds it on its next visit; bulk runs and the command rebuild it too.
- `optimized_at` timestamp in the asset's `imageoptimizer` data.
- Queue support: uploads and Glide images are optimized on the queue when the site has one (`QUEUE_CONNECTION` other than `sync`). The utility runs bulk optimizations as queued jobs and shows their progress; the asset editor button stays synchronous.
- *Optimize image* action in the asset browser, also as a bulk action.
- `please optimize:images` options `--container=`, `--only-new`, `--dry-run` and `--no-clear`.
- Dutch translations.
- Test suite (`composer test`) and GitHub Actions workflows for tests (x86_64 and ARM64) and for building the Linux binaries.

### Changed
- Optimizing an image again starts from the kept original, so repeated runs and a lower quality never stack quality loss.
- Optimizing one asset from its editor clears the Glide cache of that asset instead of the whole site.
- Bundled binaries refreshed. macOS builds are universal (Apple Silicon and Intel), built from source and working on current macOS; the 2020 builds were killed by the OS. Windows builds come from upstream releases. The 32-bit `linux-i686` and `darwin-i386` sets are gone; see `bin/BUILD.md` for provenance.
- Bundled binaries are only used when they are executable; the executable bit is restored when possible.
- The addon is built with Vite via `laravel-vite-plugin` and `@statamic/cms/vite-plugin`. The bundle shrank from 157 KB to 15 KB. Statamic republishes it when the addon is updated.

### Fixed
- Saving the asset editor after optimizing in its panel no longer writes the old statistics back over the new ones.
- Two jobs optimizing the same asset at the same time are serialized with a cache lock.
- Glide images on a non-local cache disk (`image_manipulation.cache` set to a disk name) no longer break every image request; they are optimized through the cache disk like assets are.
- Optimizing assets on S3-type disks used one temp file per file name, so two assets with the same name, or two optimizations at the same time, could overwrite each other. Every optimization now uses its own temp file.
- "Optimize all" in the utility no longer runs `cache:clear`; only the Glide cache is cleared.
- One `save()` per asset instead of two, so static cache, search index and git automation only react once.
- The utility route checks that the asset exists, is an image and that the user may edit it. Errors show a toast in the CP instead of failing silently.
- Optimizers whose binary cannot run on the server (killed, not executable, not found) are skipped instead of failing the upload; the utility marks them red with the reason.
- Replacing an asset no longer optimizes the new file twice; reuploading resets the statistics for the new file.
- The bulk-run progress poll stops when you leave the page or when a request fails.

### Upgrading from 1.x
See *Upgrading from 1.x* in the documentation: require `^2.0`; a published config is imported into the settings automatically and can be deleted. Nothing else to do.

## 1.3.0
- Statamic 6 support.
