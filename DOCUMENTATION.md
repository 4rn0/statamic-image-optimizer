## Install
Install the addon using composer:

```composer require 4rn0/statamic-image-optimizer```

Then publish the config file, translations and control panel assets:

```php artisan vendor:publish --provider="Arnohoogma\StatamicImageOptimizer\ServiceProvider" --force```

Run that command again after every update of the addon. It overwrites your published `config/statamic/imageoptimizer.php`, so if you customized it, publish only the assets instead and merge new defaults by hand:

```php artisan vendor:publish --tag=statamic-image-optimizer --force```

## Configuration
The configuration file is published to `config/statamic/imageoptimizer.php`.

| Key | Default | What it does |
| --- | --- | --- |
| `assets` | `true` | Optimize every image Asset when it is uploaded or reuploaded |
| `glide` | `true` | Optimize every Glide manipulation when it is generated |
| `log` | `false` | Write every optimizer command and its output to the Laravel log |
| `optimizers` | jpegoptim, gifsicle, pngquant, optipng | The commands to run per mimetype, see *Customization* |
| `paths` | Homebrew and the usual `/usr` locations | Extra directories to search for optimizer binaries, besides `PATH` |

## Usage
**Asset editor.** Every image Asset gets a small *ImageOptimizer* panel in its editor showing the original size and the gain, with a button to optimize it (again). The button uses the same permission as the utility below.

**Asset browser.** Select one or more images and run the *Optimize image* action.

**Utility.** *Utilities → Optimizer* shows how many images have been optimized and how much was saved, lets you optimize all images or only the new ones, and shows the status of every configured optimizer. Users need the *Access ImageOptimizer utility* permission.

**Command.** `php please optimize:images` optimizes every existing image Asset and clears the Glide cache. Options:

| Option | Effect |
| --- | --- |
| `--container=assets` | Only this container, repeat the option for more |
| `--only-new` | Skip images that were optimized before |
| `--dry-run` | List what would be optimized, change nothing |
| `--no-clear` | Keep the Glide cache |

**Queue.** When the site has a queue (`QUEUE_CONNECTION` other than `sync`) uploads, Glide images, the asset browser action and the utility's bulk runs are optimized by the queue worker. The utility shows the progress of a bulk run and its statistics when it is done. With the `sync` driver everything runs during the request, as before. The button in the asset editor always optimizes immediately, so you see the result.

**Statistics.** The sizes are stored on the Asset as `imageoptimizer` with `original_size` and `current_size`, so they are available in your templates as `{{ imageoptimizer:original_size }}` and `{{ imageoptimizer:current_size }}`.

## Optimization tools
The addon uses these optimizers when they are available on the server:

- [JpegOptim](https://github.com/tjko/jpegoptim) for JPEG
- [Pngquant](https://pngquant.org/) and [Optipng](http://optipng.sourceforge.net/) for PNG
- [Gifsicle](http://www.lcdf.org/gifsicle/) for GIF

On Ubuntu:

```bash
sudo apt-get install jpegoptim optipng pngquant gifsicle
```

On macOS with [Homebrew](https://brew.sh/):

```bash
brew install jpegoptim optipng pngquant gifsicle
```

The addon looks for the binaries in `PATH` and in these directories, which you can change in the config file:

    /opt/homebrew/bin
    /opt/homebrew/sbin
    /usr/local
    /usr/local/bin
    /usr/bin
    /usr/sbin
    /usr/local/sbin
    /bin
    /sbin

**Batteries included.** The addon ships precompiled versions of jpegoptim, pngquant, optipng and gifsicle. When an optimizer is not installed on the server, the included version is used. This works on most servers; if it does not, install the optimizer yourself as described above.

| Platform | Binaries |
| --- | --- |
| Linux x86_64 (glibc) | jpegoptim 1.5.6, pngquant 3.0.3, gifsicle 1.92, optipng 0.7.5 |
| macOS 11+, Apple Silicon and Intel (universal) | jpegoptim 1.5.6, pngquant 2.18.0, gifsicle 1.96, optipng 0.7.8 |
| Windows x64 | jpegoptim 1.5.6, pngquant 2.17.0, gifsicle 1.93, optipng 0.7.8 |

Linux on ARM (aarch64, for example AWS Graviton) has no bundled binaries: install the packages with `apt-get` as shown above. See `bin/BUILD.md` in the addon for where every binary comes from and how the macOS ones are built.

The utility screen shows the status of every optimizer:

- *green*: found on the server.
- *orange*: not found on the server, the included version will be used.
- *red*: not found at all, or found but it cannot run on this server (for example an unsigned binary on macOS, or a build for another architecture). The reason is in the tooltip and in the log. That optimization is skipped.

## WebP and AVIF
WebP and AVIF are output formats: Glide converts your JPG and PNG sources when you ask for `format="webp"` on the tag, in a preset or in `image_manipulation.defaults`, and its `q` parameter sets the file size. The addon does not re-encode those files by default. If you upload WebP sources and want them shrunk, add [cwebp](https://developers.google.com/speed/webp/docs/cwebp) to the optimizers (`brew install webp` / `apt-get install webp`):

```php
[
    'executable' => 'cwebp',
    'arguments'  => '-m 6 -pass 10 -mt -q 85 :file -o :temp',
    'mimetype'   => 'image/webp',
],
```

Keep in mind that this also re-encodes Glide's WebP output, which is already lossy. AVIF cannot be optimized in place: `avifenc` does not read AVIF input. SVGs are not touched.

## Customization
You can change the arguments of the included optimizers or add your own in the config file. Every optimizer needs the mimetype it applies to, the executable and its arguments.

Use `:file` for the full path of the image and `:temp` for a temporary output file when the tool cannot write in place. The `:temp` file replaces the image afterwards; if it is empty the image is left alone. An optimization is only written back when the result is smaller than the original.

For example, to use MozJPEG:

```php
[
    'executable' => 'cjpeg',
    'arguments'  => '-quality 85 -optimize -outfile :temp :file',
    'mimetype'   => 'image/jpeg',
],
```

## Remote disks
Assets on containers that use a non-local filesystem driver (Amazon S3, DigitalOcean Spaces, SFTP, ...) are streamed to a temporary local file, optimized and streamed back. The same applies to the Glide cache when `image_manipulation.cache` in `config/statamic/assets.php` is set to a disk name. Every Glide image is optimized once, right after Glide generates it; clearing the Glide cache resets that.

## Previously known as
This addon was previously available as `4rn0/statamic-v3-image-optimizer`. It is now just *Statamic ImageOptimizer*.
