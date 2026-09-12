# Changelog

## Unreleased

### Fixed
- Glide images on a non-local cache disk (`image_manipulation.cache` set to a disk name) no longer break every image request; they are optimized through the cache disk like assets are.
- Optimizing assets on S3-type disks used one temp file per file name, so two assets with the same name, or two optimizations at the same time, could overwrite each other. Every optimization now uses its own temp file.
- "Optimize all" in the utility no longer runs `cache:clear`; only the Glide cache is cleared.
- Statistics are computed once at the end of a bulk run instead of after every image.
- One `save()` per asset instead of two, so static cache, search index and git automation only react once.
- The utility route checks that the asset exists, is an image and that the user may edit it. Errors show a toast in the CP instead of failing silently.
- Optimizers whose binary cannot run on the server (killed, not executable, not found) are skipped instead of failing the upload; the utility marks them red with the reason.
- Replacing an asset no longer optimizes the new file twice; reuploading resets the statistics for the new file.
- Post-install publishing of config and translations used publish tags that did not exist.

### Changed
- Bundled binaries refreshed. macOS builds are now universal (Apple Silicon and Intel), built from source and working on current macOS; the 2020 builds were killed by the OS. Linux x86_64 and Windows builds come from upstream releases. The 32-bit `linux-i686` and `darwin-i386` sets are gone; see `bin/BUILD.md` for provenance.
- The addon is now built with Vite via `laravel-vite-plugin` and `@statamic/cms/vite-plugin`. The bundle shrank from 157 KB to 12 KB. Run `php artisan vendor:publish --tag=statamic-image-optimizer --force` after updating.
- Bundled binaries are only used when they are executable; the executable bit is restored when possible.

### Added
- Queue support: uploads and Glide images are optimized on the queue when the site has one (`QUEUE_CONNECTION` other than `sync`). The utility runs bulk optimizations as queued jobs and shows their progress; the asset editor button stays synchronous.
- *Optimize image* action in the asset browser, also as a bulk action.
- `please optimize:images` options `--container=`, `--only-new`, `--dry-run` and `--no-clear`.
- Dutch translations.
- Test suite (`composer test`) and GitHub Actions workflow.

## 3.x (Statamic v6 update)
- Statamic 6 support.
