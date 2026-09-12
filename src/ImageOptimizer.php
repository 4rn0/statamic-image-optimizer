<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Statamic\Contracts\Assets\Asset;
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
     * Optimize Asset, save metadata
     *
     * @param \Statamic\Contracts\Assets\Asset $asset
     * @return \Statamic\Contracts\Assets\Asset $asset
     */
    public function optimizeAsset(Asset $asset)
    {

        $data = $asset->get('imageoptimizer', []);

        $sizes = $this->optimizeFile($asset->disk()->filesystem(), $asset->path());

        $data['original_size'] ??= $sizes['before'];
        $data['current_size'] = $sizes['after'];

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

        $store = Glide::cacheStore();
        $key = 'imageoptimizer::' . $path;

        if ($store->has($key)) {

            return;

        }

        $this->optimizeFile(Glide::cacheDisk(), $path);

        $store->forever($key, true);

        // Asset manipulations live in containers/{container}/{asset path}/{hash}/{file}.
        // Register the marker in Statamic's per-asset manifest so Glide::clearAsset() forgets it too.
        if (str_starts_with($path, 'containers/')) {

            $manifest = 'asset::' . preg_replace('#^containers/([^/]+)/#', '$1::', dirname($path, 2));

            $store->forever($manifest, collect($store->get($manifest, []))->push($key)->unique()->all());

        }

    }

    /**
     * Copy a file from any filesystem to a local temp file, optimize it and write it back
     *
     * @param \Illuminate\Contracts\Filesystem\Filesystem $filesystem
     * @param string $path
     * @return array ['before' => int, 'after' => int]
     */
    private function optimizeFile(Filesystem $filesystem, $path)
    {

        $temp = tempnam(sys_get_temp_dir(), 'imageoptimizer');

        try {

            $stream = $filesystem->readStream($path);
            file_put_contents($temp, $stream);
            fclose($stream);

            $before = filesize($temp);

            $this->optimizePath($temp);

            $after = filesize($temp);

            if ($after && $after < $before) {

                $stream = fopen($temp, 'r');
                $filesystem->writeStream($path, $stream);
                fclose($stream);

            }

            return ['before' => $before, 'after' => min($before, $after ?: $before)];

        } finally {

            @unlink($temp);

        }

    }

    /**
     * Optimize image by local path
     *
     * @param string $path
     */
    private function optimizePath($path)
    {

        if (file_exists($path)) {

            $this->attemptOptimization($path);
            clearstatcache(true, $path);

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

        return $finder->find($binary, $this->executable($bundled), config('statamic.imageoptimizer.paths', [

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
     * @return string|false $binary
     */
    public function findBundledBinary($name)
    {

        $directory = match (PHP_OS_FAMILY) {
            'Linux' => php_uname('m') === 'x86_64' ? 'linux-x86_64' : null,
            'Darwin' => 'darwin',
            'Windows' => 'windows',
            default => null,
        };

        if (!$directory) {

            return false;

        }

        return realpath(__DIR__ . '/../bin/' . $directory . '/' . $name . (PHP_OS_FAMILY === 'Windows' ? '.exe' : ''));

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

        if (config('statamic.imageoptimizer.log')) {

            Log::info($message, $context);

        }

    }

}
