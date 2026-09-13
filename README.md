# ImageOptimizer for Statamic

ImageOptimizer runs every JPEG, PNG, GIF and WebP you upload, and every image Glide generates, through the best free tools there are (jpegoptim, pngquant, optipng, gifsicle, cwebp). Your files keep their names, URLs and dimensions; they just load faster. Just what you needed to get those Google Pagespeed bonus points! 🤘

## What you get

- **Automatic.** Uploads and Glide manipulations are optimized as they happen, on the queue when the site has one. Existing images are handled from the asset browser, the utility or the command line.
- **Reversible.** The original of every image is kept until you say otherwise. Revert one image from its editor or a hundred from the asset browser. Optimizing again always starts from the original, so quality never degrades over time.
- **Visible.** A savings report per asset container: how many images, how much smaller, what the kept originals cost. Export it as CSV or print it for a client.
- **Batteries included.** Binaries for Linux x86_64 and ARM64 (Graviton, Hetzner CAX, Ampere), macOS (Apple Silicon and Intel) and Windows are bundled and used whenever the server has none of its own.
- **Configurable from the control panel.** Toggles, originals and, for admins, the optimizer commands and search paths, each with a live status of the binary. No config publishing, no manual merges after updates.
- **Works with remote disks.** Assets on S3, Spaces or SFTP are optimized through a temporary local file; originals stay on the same disk.

## New in 2.0

Version 2.0 is an overhaul. Everything from 1.x still works and existing licences upgrade for free, see the [changelog](CHANGELOG.md) for the details and the upgrade notes.

- Originals are kept; revert and discard actions, a revert button in the asset editor.
- Savings report with CSV export and a print view.
- All settings on the utility page; the config file is gone (a published one is imported on upgrade).
- WebP optimization with bundled cwebp.
- Bundled binaries for Linux ARM64; refreshed, universal macOS binaries.
- Queue support with progress, a bulk action in the asset browser, more command options.
- Statamic 6, Vite build, a test suite that runs on x86_64 and ARM64.

## Requirements

Statamic 6.5 or later, PHP 8.3 with the fileinfo extension. Nothing else: the optimizers are bundled.

## ImageOptimizer is a commercial addon

You can use it for free while in development, but it requires a license to use on a live site. Learn more or buy a license on [The Statamic Marketplace](https://statamic.com/addons/4rn0/imageoptimizer)! The full documentation is in [DOCUMENTATION.md](DOCUMENTATION.md).
