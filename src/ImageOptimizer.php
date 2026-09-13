<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Glide;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

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
     * Optimize Asset, save metadata. Keeps the original the first time, optimizes from that
     * original every time after, so repeated runs never stack generation loss.
     *
     * @param \Statamic\Contracts\Assets\Asset $asset
     * @return \Statamic\Contracts\Assets\Asset $asset
     */
    public function optimizeAsset(Asset $asset)
    {

        $this->locked($asset, function () use ($asset) {

            $data = $asset->get('imageoptimizer', []);
            $filesystem = $asset->disk()->filesystem();
            $settings = Settings::for($asset->container());

            // The stored original is gone: forget it and go on with the current file
            if (isset($data['original']) && !$filesystem->exists($data['original'])) {

                unset($data['original']);

            }

            // Only bytes the addon never touched can serve as the original, and only once
            if (!$data && $settings['originals']) {

                $data['original'] = $this->storeOriginal($asset);

            }

            $sizes = $this->optimizeFile($filesystem, $asset->path(), $settings, $data['original'] ?? null);

            $data['original_size'] ??= $sizes['before'];
            $data['current_size'] = $sizes['after'];
            $data['optimized_at'] = now()->timestamp;

            $asset->set('imageoptimizer', $data);
            $asset->save();

            Report::touch();

        });

        return $asset;

    }

    /**
     * Put the stored original back, forget the statistics and clear the Asset's Glide cache
     *
     * @param \Statamic\Contracts\Assets\Asset $asset
     * @return bool whether there was an original to revert to
     */
    public function revertAsset(Asset $asset)
    {

        return (bool) $this->locked($asset, function () use ($asset) {

            $data = $asset->get('imageoptimizer', []);
            $filesystem = $asset->disk()->filesystem();

            if (!$original = $data['original'] ?? null) {

                return false;

            }

            if (!$filesystem->exists($original)) {

                Log::warning('ImageOptimizer: the stored original of ' . $asset->id() . ' is gone: ' . $original);

                unset($data['original']);

                $asset->set('imageoptimizer', $data);
                $asset->save();

                return false;

            }

            $temp = tempnam(sys_get_temp_dir(), 'imageoptimizer');

            try {

                $this->download($filesystem, $original, $temp);
                $this->upload($temp, $filesystem, $asset->path());

            } finally {

                @unlink($temp);

            }

            $filesystem->delete($original);

            $asset->remove('imageoptimizer');
            $asset->save();

            Glide::clearAsset($asset);
            Report::touch();

            return true;

        });

    }

    /**
     * Delete the stored original, keep the statistics
     *
     * @param \Statamic\Contracts\Assets\Asset $asset
     * @return \Statamic\Contracts\Assets\Asset $asset
     */
    public function discardOriginal(Asset $asset)
    {

        $data = $asset->get('imageoptimizer', []);

        if (isset($data['original'])) {

            $this->deleteOriginal($asset);

            unset($data['original']);

            $asset->set('imageoptimizer', $data);
            $asset->save();

            Report::touch();

        }

        return $asset;

    }

    /**
     * Delete the stored original file of an Asset that is deleted or reuploaded
     *
     * @param \Statamic\Contracts\Assets\Asset $asset
     */
    public function deleteOriginal(Asset $asset)
    {

        if ($original = $asset->get('imageoptimizer')['original'] ?? null) {

            $asset->disk()->filesystem()->delete($original);

        }

    }

    /**
     * Optimize Glide image if necessary
     *
     * @param string $path
     */
    public function optimizeGlide($path)
    {

        $store = Glide::cacheStore();
        $key = 'imageoptimizer::' . $path;

        if ($store->has($key)) {

            return;

        }

        // Asset manipulations live in containers/{container}/{asset path}/{hash}/{file}
        $container = str_starts_with($path, 'containers/') ? AssetContainer::find(explode('/', $path)[1]) : null;

        $this->optimizeFile(Glide::cacheDisk(), $path, Settings::for($container));

        $store->forever($key, true);

        // Register the marker in Statamic's per-asset manifest so Glide::clearAsset() forgets it too.
        if (str_starts_with($path, 'containers/')) {

            $manifest = 'asset::' . preg_replace('#^containers/([^/]+)/#', '$1::', dirname($path, 2));

            $store->forever($manifest, collect($store->get($manifest, []))->push($key)->unique()->all());

        }

    }

    /**
     * Copy the Asset into the container's .meta folder, the one place Statamic hides from both the
     * asset browser and the Stache (a subfolder of it would not be). The name is random on purpose:
     * it survives renames and moves, only the pointer in the Asset's data travels along.
     *
     * @param \Statamic\Contracts\Assets\Asset $asset
     * @return string path of the copy
     */
    private function storeOriginal(Asset $asset)
    {

        $path = '.meta/imageoptimizer-' . Str::uuid() . '.' . $asset->extension();

        $asset->disk()->filesystem()->copy($asset->path(), $path);

        return $path;

    }

    /**
     * Copy a file from any filesystem to a local temp file, optimize it and write it back.
     * With a source, that file is optimized and the result written to the path instead.
     *
     * @param \Illuminate\Contracts\Filesystem\Filesystem $filesystem
     * @param string $path
     * @param array $settings
     * @param string|null $source
     * @return array ['before' => int, 'after' => int]
     */
    private function optimizeFile(Filesystem $filesystem, $path, array $settings, $source = null)
    {

        $temp = tempnam(sys_get_temp_dir(), 'imageoptimizer');

        try {

            $this->download($filesystem, $source ?? $path, $temp);

            $before = filesize($temp);

            $this->optimizePath($temp, $settings);

            $after = filesize($temp);

            if ($after && $after < $before) {

                $this->upload($temp, $filesystem, $path);

                return ['before' => $before, 'after' => $after];

            }

            return ['before' => $before, 'after' => $filesystem->size($path)];

        } finally {

            @unlink($temp);

        }

    }

    /**
     * @param \Illuminate\Contracts\Filesystem\Filesystem $filesystem
     * @param string $path
     * @param string $temp
     */
    private function download(Filesystem $filesystem, $path, $temp)
    {

        $stream = $filesystem->readStream($path);
        file_put_contents($temp, $stream);
        fclose($stream);

    }

    /**
     * @param string $temp
     * @param \Illuminate\Contracts\Filesystem\Filesystem $filesystem
     * @param string $path
     */
    private function upload($temp, Filesystem $filesystem, $path)
    {

        $stream = fopen($temp, 'r');
        $filesystem->writeStream($path, $stream);
        fclose($stream);

    }

    /**
     * Run a callback while holding a lock on the Asset, so two jobs cannot store an already
     * optimized file as the original. Skipped when the lock is taken or the cache has no locks.
     *
     * @param \Statamic\Contracts\Assets\Asset $asset
     * @param callable $callback
     * @return mixed null when skipped
     */
    private function locked(Asset $asset, callable $callback)
    {

        if (!Cache::getStore() instanceof LockProvider) {

            return $callback();

        }

        $lock = Cache::lock('imageoptimizer::lock::' . $asset->id(), 120);

        if (!$lock->get()) {

            $this->log('ImageOptimizer: ' . $asset->id() . ' is locked, skipped');

            return null;

        }

        try {

            return $callback();

        } finally {

            $lock->release();

        }

    }

    /**
     * Optimize image by local path
     *
     * @param string $path
     * @param array $settings
     */
    private function optimizePath($path, array $settings)
    {

        if (file_exists($path)) {

            $this->attemptOptimization($path, $settings);
            clearstatcache(true, $path);

        }

    }

    /**
     * Attempt image optimizations
     *
     * @param string $path
     * @param array $settings
     */
    private function attemptOptimization($path, array $settings)
    {

        $optimizers = $settings['optimizers'];
        $filetype = mime_content_type($path);

        // Re-encoding would make lossless WebP lossy, and cwebp cannot read animations anyway
        if ($filetype === 'image/webp' && $this->isLosslessOrAnimatedWebp($path)) {

            $this->log('ImageOptimizer: skipped lossless or animated WebP');

            return;

        }

        foreach ($optimizers as $optimizer) {

            if ($optimizer['mimetype'] === $filetype) {

                $tempfile = false;

                if (!$binary = $this->findBinary($optimizer['executable'])) {

                    $this->log('ImageOptimizer: no executable found for ' . $optimizer['executable']);

                    continue;

                }

                $command = str_replace(':file', escapeshellarg($path), $binary . ' ' . $optimizer['arguments']);

                if (strpos($command, ':temp') !== false) {

                    $tempfile = tempnam(sys_get_temp_dir(), 'imageoptimizer');
                    $command = str_replace(':temp', escapeshellarg($tempfile), $command);

                }

                $this->optimize($command, function() use ($tempfile, $path) {

                    if ($tempfile && filesize($tempfile)) {

                        rename($tempfile, $path);

                    }

                });

                if ($tempfile) {

                    @unlink($tempfile);

                }

            }

        }

    }

    /**
     * Walk the RIFF chunks of a WebP file: VP8L is lossless, the animation flag lives in VP8X
     *
     * @param string $path
     * @return bool
     */
    public function isLosslessOrAnimatedWebp($path)
    {

        $handle = fopen($path, 'rb');

        try {

            $riff = fread($handle, 12);

            if (strlen($riff) < 12 || substr($riff, 0, 4) !== 'RIFF' || substr($riff, 8, 4) !== 'WEBP') {

                return false;

            }

            while (strlen($chunk = fread($handle, 8)) === 8) {

                $fourcc = substr($chunk, 0, 4);
                $size = unpack('V', substr($chunk, 4))[1];

                if ($fourcc === 'VP8L') {

                    return true;

                }

                if ($fourcc === 'VP8 ') {

                    return false;

                }

                if ($fourcc === 'VP8X' && (ord(fread($handle, 1)) & 0x02)) {

                    return true;

                }

                // Chunks are padded to an even size; VP8X already consumed its flags byte
                fseek($handle, $size + ($size % 2) - ($fourcc === 'VP8X' ? 1 : 0), SEEK_CUR);

            }

            return false;

        } finally {

            fclose($handle);

        }

    }

    /**
     * Where an optimizer's binary is and whether it works: found, bundled, broken or missing
     *
     * @param string $executable
     * @return array ['path' => string|null, 'status' => string]
     */
    public function status($executable)
    {

        $bundled = $this->findBundledBinary($executable);
        $path = $this->findBinary($executable) ?: $bundled;

        return [
            'path' => $path ?: null,
            'status' => match (true) {
                !$path => 'missing',
                !$this->canRun($path) => 'broken',
                $path === $bundled => 'bundled',
                default => 'found',
            },
        ];

    }

    /**
     * Find executable binary for optimizer: on the system first, bundled as a fallback
     *
     * @param string $name
     * @return string|null $binary
     */
    public function findBinary($name)
    {

        $finder = new ExecutableFinder();

        $bundled = $this->findBundledBinary($name);
        $binary = basename($name);

        return $finder->find($binary, $this->executable($bundled), Settings::get('paths') ?? []);

    }

    /**
     * Find bundled binary for optimizer
     *
     * @param string $name
     * @return string|false $binary
     */
    public function findBundledBinary($name)
    {

        if (!$directory = static::bundledDirectory(PHP_OS_FAMILY, php_uname('m'))) {

            return false;

        }

        return realpath(__DIR__ . '/../bin/' . $directory . '/' . $name . (PHP_OS_FAMILY === 'Windows' ? '.exe' : ''));

    }

    /**
     * The bin/ subdirectory for an OS family and machine type (php_uname('m'))
     *
     * @param string $family
     * @param string $machine
     * @return string|null
     */
    public static function bundledDirectory($family, $machine)
    {

        return match (true) {
            $family === 'Linux' && in_array($machine, ['x86_64', 'amd64']) => 'linux-x86_64',
            $family === 'Linux' && in_array($machine, ['aarch64', 'arm64']) => 'linux-aarch64',
            $family === 'Darwin' => 'darwin',
            $family === 'Windows' => 'windows',
            default => null,
        };

    }

    /**
     * Whether this server can actually run a binary: unsigned or foreign-architecture
     * builds pass is_executable() but get killed. Probed once per build and remembered,
     * so macOS Gatekeeper dialogs and killed processes don't repeat on every request.
     *
     * @param string $binary
     * @return bool
     */
    public function canRun($binary)
    {

        $key = 'imageoptimizer::binary::' . md5($binary . @filemtime($binary) . @filesize($binary));

        return Cache::rememberForever($key, fn () => $this->probe($binary));

    }

    /**
     * Launch a binary with --version and see whether it survives
     *
     * @param string $binary
     * @return bool
     */
    private function probe($binary)
    {

        $process = new Process([$binary, '--version']);
        $process->setTimeout(5);

        try {

            $process->run();

        } catch (ProcessSignaledException|ProcessTimedOutException|ProcessRuntimeException $e) {

            Log::warning('ImageOptimizer: ' . $binary . ' cannot run: ' . $e->getMessage());

            return false;

        }

        // 126 = not executable, 127 = not found. Any other exit code means it ran.
        if (in_array($process->getExitCode(), [126, 127])) {

            Log::warning('ImageOptimizer: ' . $binary . ' cannot run: ' . $process->getErrorOutput());

            return false;

        }

        return true;

    }

    /**
     * Only fall back to a bundled binary that can actually run. Composer dist installs lose the executable bit.
     *
     * @param string|false $binary
     * @return string|null
     */
    private function executable($binary)
    {

        if (!$binary || !is_file($binary)) {

            return null;

        }

        if (!is_executable($binary)) {

            @chmod($binary, 0755);

        }

        return is_executable($binary) && $this->canRun($binary) ? $binary : null;

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

        try {

            $process->run();

        } catch (ProcessSignaledException|ProcessTimedOutException $e) {

            Log::warning('ImageOptimizer: ' . $e->getMessage());

            return false;

        }

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

        if (Settings::get('log')) {

            Log::info($message, $context);

        }

    }

}
