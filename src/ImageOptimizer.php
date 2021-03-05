<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;

class ImageOptimizer
{

    /**
     * This one goes to eleven.
     */
    public function __construct()
    {

        @ini_set('memory_limit', config('statamic.system.php_memory_limit'));
        @set_time_limit(config('statamic.system.php_max_execution_time'));

    }

    /**
     * Optimize Asset, save metadata
     *
     * @param Statamic\Assets\Asset $asset
     * @return Statamic\Assets\Asset $asset
     */
    public function optimizeAsset(Asset $asset)
    {

        $path = $this->ensureOnLocalFileSystem($asset);

        $this->setOriginalSize($asset);
        $this->optimizePath($path);
        $this->ensureOnCorrectFileSystem($asset);
        $this->setCurrentSize($asset);

        return $asset;

    }

    /**
     * Copy Asset to local filesystem if necessary
     *
     * @param Statamic\Assets\Asset $asset
     * @return string $path
     */
    private function ensureOnLocalFileSystem(Asset $asset)
    {

        $path = $asset->resolvedPath();

        if (!$this->isOnLocalFileSystem($asset))
        {

            $source = $asset->disk()->filesystem()->readStream($path);
            $target = $asset->basename();
            
            Storage::disk('local')->putStream($target, $source);

            $path = Storage::disk('local')->path($target);

        }

        return $path;

    }

    /**
     * Move optimized Asset from local to remote filesystem
     *
     * @param Statamic\Assets\Asset $asset
     * @return Statamic\Assets\Asset $asset
     */
    private function ensureOnCorrectFileSystem(Asset $asset)
    {

        if (!$this->isOnLocalFileSystem($asset))
        {

            $path = $asset->basename();

            $source = Storage::disk('local')->readStream($path);
            $target = $asset->resolvedPath();

            $asset->disk()->filesystem()->putStream($target, $source);

            Storage::disk('local')->delete($path);

        }

        return $asset;

    }

    /**
     * Check if Asset is on local filesystem
     *
     * @param Statamic\Assets\Asset $asset
     * @return bool
     */
    private function isOnLocalFileSystem(Asset $asset)
    {

        $disk = $asset->container()->toArray()['disk'];
        $driver = config('filesystems.disks.' . $disk . '.driver');

        return in_array($driver, ['local']);

    }

    /**
     * Sets Asset's original size
     *
     * @param Statamic\Assets\Asset $asset
     * @return Statamic\Assets\Asset $asset
     */
    private function setOriginalSize(Asset $asset)
    {

        $data = $asset->get('imageoptimizer', []);

        if (!isset($data['original_size'])) {

            $data['original_size'] = $asset->disk()->size( $asset->path() );
    
            $asset->set('imageoptimizer', $data);
            $asset->save();

        }

        return $asset;

    }

    /**
     * Sets Asset's current size
     *
     * @param Statamic\Assets\Asset $asset
     * @return Statamic\Assets\Asset $asset
     */
    private function setCurrentSize(Asset $asset)
    {

        $data = $asset->get('imageoptimizer', []);

        $size = ['current_size' => $asset->disk()->size( $asset->path() )];
        $data = array_merge($data, $size);

        $asset->set('imageoptimizer', $data);
        $asset->save();

        return $asset;
        
    }

    /**
     * Optimize Glide image if necessary
     *
     * @param string $path
     */
    public function optimizeGlide($path)
    {
        
        $cache_key = 'imageoptimizer:' . $path;

        $base = config('statamic.assets.image_manipulation.cache') ? config('statamic.assets.image_manipulation.cache_path') : storage_path('statamic/glide');
        $path = realpath($base . '/' . $path);
        
        if (cache()->get($cache_key, false) !== filemtime($path))
        {
            
            $this->optimizePath($path);
            cache()->put($cache_key, filemtime($path));

        }

    }

    /**
     * Optimize Asset image by path, save statistics
     *
     * @param string $path
     */
    private function optimizePath($path)
    {

        if (file_exists($path)) {

            $this->attemptOptimization($path);
            clearstatcache($path);

        }

    }

    /**
     * Attempt image optimizations
     *
     * @param string $path
     */
    private function attemptOptimization($path)
    {

        $optimizers = config('statamic.imageoptimizer.optimizers');
        $filetype = mime_content_type($path);

        foreach ($optimizers as $optimizer)
        {

            if ($optimizer['mimetype'] === $filetype)
            {

                $tempfile = false;

                $command = $this->getCommand($optimizer['executable'], $optimizer['arguments']);
                $command = str_replace(':file', escapeshellarg($path), $command);

                if (strpos($command, ':temp') !== false)
                {

                    $tempfile = tempnam(sys_get_temp_dir(), 'imageoptimizer');
                    $command = str_replace(':temp', escapeshellarg($tempfile), $command);

                }

                $this->optimize($command, function() use ($tempfile, $path)
                {

                    if ($tempfile && filesize($tempfile))
                    {

                        rename($tempfile, $path);

                    }

                });

            }

        }

    }

    /**
     * Create optimizer command
     *
     * @param string $executable
     * @param string $arguments
     * @return string $command
     */
    private function getCommand($executable, $arguments)
    {

        $binary = $this->findBinary($executable);
        $command = $binary . ' ' . $arguments;

        return $command;

    }

    /**
     * Find executable binary for optimizer
     *
     * @param string $name
     * @return string $binary
     */
    public function findBinary($name)
    {

        $finder = new ExecutableFinder();

        $included = $this->findBundledBinary($name);
        $binary = basename($name);

        return $finder->find($binary, $included, config('statamic.imageoptimizer.paths', [

            '/opt/homebrew/bin',
            '/opt/homebrew/sbin',
            '/usr/local',
            '/usr/local/bin',
            '/usr/bin',
            '/usr/sbin',
            '/usr/local/bin',
            '/usr/local/sbin',
            '/bin',
            '/sbin'

        ]));

    }

    /**
     * Find bundled binary for optimizer
     *
     * @param string $name
     * @return string $binary
     */
    private function findBundledBinary($name)
    {

        if (in_array(PHP_OS, ['Linux'])) {

            return realpath(__DIR__  . '/../bin/linux-' . (PHP_INT_SIZE === 8 ? 'x86_64' : 'i686') . '/' . $name);

        }

        if (in_array(PHP_OS, ['Darwin'])) {

            return realpath(__DIR__  . '/../bin/darwin-' . (PHP_INT_SIZE === 8 ? 'x86_64' : 'i386') . '/' . $name);

        }

        if (in_array(PHP_OS, ['WIN32', 'WINNT', 'Windows'])) {

            return realpath(__DIR__  . '/../bin/windows/' . $name . '.exe');

        }

        return $name;

    }

    /**
     * Execute optimizer command
     *
     * @param string $command
     * @param \Closure|null $callback
     * @return bool $result
     */
    private function optimize($command, $callback = null)
    {

        $process = Process::fromShellCommandline($command);

        $process->setTimeout(60);
        $process->enableOutput();
        $process->run();

        if ($process->isSuccessful() && is_callable($callback)) {

            $callback();

        }

        $output = $process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput();

        $this->log('ImageOptimizer command: ' . $command);
        $this->log($output);

        return $process->isSuccessful();

    }

    /**
     * Log optimizations
     *
     * @param string $message
     * @param array $context
     */
    private function log($message, $context = [])
    {

        if (config('statamic.imageoptimizer.log'))
        {

            Log::info($message, $context);   

        }

    }

}
