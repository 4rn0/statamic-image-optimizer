## Install
```composer require 4rn0/statamic-image-optimizer```

That is all. Nothing needs publishing: Statamic publishes the control panel assets when it installs or updates the addon, and there is no configuration file, everything is set in the control panel. Requires Statamic 6.5 or later and PHP 8.3. Upgrading from 1.x? See *Upgrading from 1.x* at the end.

## Settings
The settings form is on the utility page, *Utilities → Optimizer*, together with the savings report. Users need the *Access ImageOptimizer utility* permission, the same one that allows bulk optimization; super users have it.

| Setting | Default | What it does |
| --- | --- | --- |
| Optimize Assets | on | Optimize every image Asset when it is uploaded or reuploaded |
| Optimize Glide | on | Optimize every Glide manipulation when it is generated |
| Keep originals | on | Keep a copy of every image before its first optimization, so it can be reverted. See *Originals and revert* |
| Log optimizations | off | Write every optimizer command and its output to the Laravel log |

The settings are stored in `resources/addons/statamic-image-optimizer.yaml`. Commit and deploy that file like the rest of `resources/`. Until you save the form, the defaults apply.

**Optimizers and search paths.** The form has a second section with the optimizer commands (executable, arguments, mimetype, one row per optimizer) and the directories to search for binaries. It is only shown to super users and to roles with the *Edit ImageOptimizer optimizers* permission, because these are shell commands: whoever can edit them can run any program on the server. Every row starts with a dot showing whether that binary was found on the server (green), will come from the bundled set (orange) or is missing or broken (red); a newly typed executable gets its dot after saving. See *Customizing the optimizers* for the placeholders.

## How it works
**Uploads.** Every image Asset is optimized right after it is uploaded or reuploaded, on the queue when the site has one. The file is replaced in place; the sizes are stored on the Asset.

**Glide.** Every manipulation Glide generates is optimized once, right after it is generated, on the queue when the site has one. Clearing the Glide cache resets that.

**Only smaller results are kept.** An optimizer's output is written back only when it is smaller than the input; otherwise the file is left alone.

**Originals and revert.** The first time an image is optimized, a copy of the untouched file is kept in the hidden `.meta/` folder of its container, next to Statamic's own metadata, on the same disk (local or S3). Every later optimization starts from that copy, so optimizing again, or again with a lower quality in the arguments, never stacks quality loss.

- **Revert** puts the original back, forgets the statistics and clears the Glide cache of that image. Available in the asset editor panel and as the *Revert to original* action in the asset browser.
- **Discard original** deletes the copy and keeps the statistics, to free storage. Action in the asset browser.
- Renaming, moving and deleting images is handled; reuploading an image replaces its original.

Keeping originals costs as much storage as the original images. Turn *Keep originals* off in the settings if you do not want that. Images optimized before version 2.0 have no stored original and cannot be reverted; they never get one, because only a file the addon has not touched yet is kept. On a local disk the copies are inside the public assets folder like the `.meta` files themselves, with random names.

## Using it
**Asset editor.** Every image Asset gets a small *ImageOptimizer* panel in its editor showing the original size and the gain, whether the original is kept, and buttons to optimize it (again) and to revert it. The buttons need permission to edit the asset and the *Access ImageOptimizer utility* permission. This panel always works immediately, also on sites with a queue, so you see the result.

**Asset browser.** Select one or more images and run the *Optimize image*, *Revert to original* or *Discard original* action. The last two only appear for images with a kept original.

**Utility and savings report.** *Utilities → Optimizer* shows the savings report: per asset container the number of images, how many are optimized, the original and current size, the saving, the storage taken by kept originals and the last optimization date, with a total (a site with one container just gets that one row). The report is stored and only recomputed when something changed: an upload, an optimization, a revert or a deletion marks it stale and the next visit to the page rebuilds it; bulk runs and the command rebuild it as well. With thousands of images that rebuild takes a moment, once. *Export CSV* downloads one row per image for a spreadsheet; printing the page (or saving it as PDF) gives a clean report for a client, the buttons, settings and optimizer table are left out. The same screen lets you optimize all images or only the new ones and, for users who cannot edit the optimizers, shows a read-only table with the status of every configured one. Users need the *Access ImageOptimizer utility* permission.

Statamic asset containers are not tied to sites, so the report is per container; use the site name as the heading when you show it to a client.

**Command.** `php please optimize:images` optimizes every existing image Asset, clears the Glide cache and rebuilds the report. Options:

| Option | Effect |
| --- | --- |
| `--container=assets` | Only this container, repeat the option for more |
| `--only-new` | Skip images that were optimized before |
| `--dry-run` | List what would be optimized, change nothing |
| `--no-clear` | Keep the Glide cache |

**Queue.** When the site has a queue (`QUEUE_CONNECTION` other than `sync`) uploads, Glide images, the asset browser actions and the utility's bulk runs are handled by the queue worker. The utility shows the progress of a bulk run and the new report when it is done. With the `sync` driver everything runs during the request.

## Formats and binaries
| Format | Tool | What happens |
| --- | --- | --- |
| JPEG | [jpegoptim](https://github.com/tjko/jpegoptim) | Progressive, recompressed to quality 85 when above it (`-m85` in its arguments), metadata stripped |
| PNG | [pngquant](https://pngquant.org/) then [optipng](http://optipng.sourceforge.net/) | Palette reduction, then lossless recompression |
| GIF | [gifsicle](http://www.lcdf.org/gifsicle/) | Lossless recompression, animations included |
| WebP | [cwebp](https://developers.google.com/speed/webp/docs/cwebp) | Re-encoded at quality 85 (`-q 85` in its arguments). Lossless and animated WebP files are left alone |
| AVIF, SVG | — | Not touched. AVIF comes out of Glide already small and `avifenc` cannot read AVIF input; re-encoding one would only lose quality |

Glide's own WebP output (`format="webp"` on the tag, in a preset or in `image_manipulation.defaults`) is re-encoded once too, the same way jpegoptim recompresses Glide's JPEGs. If you would rather not, raise its `-q` or remove the cwebp entry from the optimizers.

**Batteries included.** The addon ships precompiled versions of all five tools. A tool installed on the server is used first; the included version is the fallback. Install the tools yourself when you want a specific version:

```bash
sudo apt-get install jpegoptim optipng pngquant gifsicle webp   # Ubuntu / Debian
brew install jpegoptim optipng pngquant gifsicle webp           # macOS
```

| Platform | Bundled binaries |
| --- | --- |
| Linux x86_64 (glibc) | jpegoptim 1.5.6, pngquant 3.0.3, gifsicle 1.92, optipng 0.7.5, cwebp 1.6.0 |
| Linux ARM64 (aarch64: AWS Graviton, Hetzner CAX, Ampere) | jpegoptim 1.5.6, pngquant 2.18.0, gifsicle 1.96, optipng 0.7.8, cwebp 1.6.0 (static) |
| macOS 11+, Apple Silicon and Intel (universal) | jpegoptim 1.5.6, pngquant 2.18.0, gifsicle 1.96, optipng 0.7.8, cwebp 1.6.0 |
| Windows x64 | jpegoptim 1.5.6, pngquant 2.17.0, gifsicle 1.93, optipng 0.7.8, cwebp 1.6.0 |

The addon looks for binaries in `PATH` and in `/opt/homebrew/bin`, `/opt/homebrew/sbin`, `/usr/local/bin`, `/usr/local/sbin`, `/usr/bin`, `/usr/sbin`, `/bin` and `/sbin`; the list is the *Search paths* setting. `bin/BUILD.md` in the addon says where every binary comes from and how they are built.

The utility screen shows the status of every optimizer:

- *green*: found on the server.
- *orange*: not found on the server, the included version will be used.
- *red*: not found at all, or found but it cannot run on this server (for example an unsigned binary on macOS, or a build for another architecture). The reason is in the tooltip and in the log. That optimization is skipped.

## Customizing the optimizers
The *Optimizers* section of the settings form (see *Settings*) holds one row per optimizer: the executable, its arguments and the mimetype it applies to. Change the arguments of the bundled tools, add a tool, or remove one. Use `:file` for the full path of the image and `:temp` for a temporary output file when the tool cannot write in place. The `:temp` file replaces the image afterwards; if it is empty the image is left alone. For example, to use MozJPEG instead of jpegoptim:

| executable | arguments | mimetype |
| --- | --- | --- |
| `cjpeg` | `-quality 85 -optimize -outfile :temp :file` | `image/jpeg` |

The *Search paths* setting lists the directories searched for the executables besides `PATH`.

The translations can be published with `php artisan vendor:publish --tag=imageoptimizer-lang`.

## Remote disks
Assets on containers that use a non-local filesystem driver (Amazon S3, DigitalOcean Spaces, SFTP, ...) are streamed to a temporary local file, optimized and streamed back. Their originals are kept on the same remote disk, in `.meta/`. The same applies to the Glide cache when `image_manipulation.cache` in `config/statamic/assets.php` is set to a disk name.

## Templates
The sizes are stored on the Asset as `imageoptimizer`, so they are available in your templates:

```
{{ imageoptimizer:original_size }}                  bytes before the first optimization
{{ imageoptimizer:current_size }}                   bytes now
{{ imageoptimizer:optimized_at format="Y-m-d" }}    when it was last optimized (Unix timestamp)
```

## Upgrading from 1.x
1. `composer require 4rn0/statamic-image-optimizer:^2.0`. Statamic 6.5 or later is required. The control panel assets are republished by `statamic:install`; no `vendor:publish` needed.
2. The configuration file is gone. If you had published `config/statamic/imageoptimizer.php`, the update runs a script that copies its values into `resources/addons/statamic-image-optimizer.yaml` once, adds the new cwebp optimizer for WebP, and tells you so in the console; the file is no longer read and can be deleted. Check the *Optimizers* section on the utility page afterwards. If the script did not run (it needs the `statamic:install` step in your `composer.json` scripts, which every Statamic site has), run `php please updates:run 1.3.0`.
3. `public/vendor/statamic-image-optimizer/js/` (the 1.x script) can be deleted.
4. Existing `imageoptimizer` data on your assets is reused as-is. Images optimized under 1.x have no kept original and cannot be reverted.

## Previously known as
This addon was previously available as `4rn0/statamic-v3-image-optimizer`. It is now just *Statamic ImageOptimizer*.
