## Install
First, install the addon using composer  

```composer require 4rn0/statamic-image-optimizer```

Then, publish the assets by running  

```php artisan vendor:publish --provider="Arnohoogma\StatamicImageOptimizer\ServiceProvider" --force```

*Please note* that before Statamic 4 was unleashed this addon was available as `4rn0/statamic-v3-image-optimizer`. Much like Statamic is now just 'Statamic', the 'v3' part was dropped from this addon's name repository, etc. It will just be known as 'Statamic ImageOptimizer' going forward.

## Configuration
ImageOptimizer comes with a configuration file, which you can find at `config/statamic/imageoptimizer.php` after publishing the assets.  

In this file you can configure whether the addon should automatically optimize *image Assets*, whether it should optimize *Glide image manipulations*, whether it should *log detailed information* about those optimizations and which optimizer commands it should run on which types of images.  

## Usage
This addon dynamically adds a *Fieldtype* to the Asset editor, with which you can the image optimization gains and run optimizations on it.  

It also adds a *Utility* screen, where you can view all optimization gains as well as the addon's current settings and configured optimizers. It provides an option to run the optimization process on all Assets again.  

Finally, it adds a `please optimize:images` *Command* which optimizes all your existing image Assets and clears the Glide cache.  

## Optimization tools
The addon will use the following optimizers if they are available on your system:

- [JpegOptim](http://freecode.com/projects/jpegoptim)
- [Optipng](http://optipng.sourceforge.net/)
- [Pngquant 2](https://pngquant.org/)
- [Gifsicle](http://www.lcdf.org/gifsicle/)

Here's how to install all the optimizers on Ubuntu:

```bash
sudo apt-get install jpegoptim
sudo apt-get install optipng
sudo apt-get install pngquant
sudo apt-get install gifsicle
```

Here's how to install the optimizers on MacOS (using [Homebrew](https://brew.sh/)):

```bash
brew install jpegoptim
brew install optipng
brew install pngquant
brew install gifsicle
```

ImageOptimizer will try to find the executables in the following paths on your server, so please make sure you install the optimizers within these paths. You can customize these paths in the configuration file.

    /usr/local
    /usr/local/bin
    /usr/bin
    /usr/sbin
    /usr/local/bin
    /usr/local/sbin
    /bin
    /sbin

**Sounds pretty technical, huh? Don't worry: ImageOptimizer comes with batteries included!** 🔋⚡ 

The addon includes precompiled versions of these optimizers for Linux, MacOS and Windows. If an optimizer is not available on your server it will try to use the included version. This will work with most servers and configurations, but if for some reason it doesn't, you should try to install the optimizers using the above instructions.

You can see the 'status' of each optimizer command in the addon's utility screen. A *green dot* means the optimizer has been found on the server. An *orange dot* means the optimizer has not been found, so ImageOptimizer will try to use the included version during image optimizations. A *red dot* means the optimizer has not been found and a precompiled version of it is not available.  

## Customization
Aside from using the included optimizers it is also possible to change their default configuration or add some custom optimization tools in the addon's configuration file. For each optimizer you will have to provide the mimetype of the images you want it to optimize and the command and arguments you would like to run on the server.

You can use `:file` to reference the full path to the image you are optimizing and `:temp` to use a temporary output file if the optimizer requires it. The contents of the `:temp` file will automatically be copied back to the original file after the optimization.

So for example, if you would like to use MozJPEG you could add the following to the configuration file:

```php
[

    'executable' => 'mozjpeg',
    'arguments'  => '-quality 85 -optimize -outfile :temp :file',
    'mimetype'   => 'image/jpeg',

],
```  

Images in Asset containers that are not using the `local` filesystem driver will be copied to the local filesystem before optimization and copied back to their original filesystem afterwards, so you can safely use Amazon S3 or other drivers.