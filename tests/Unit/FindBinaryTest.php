<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Tests\TestCase;

class FindBinaryTest extends TestCase
{

    public function test_it_finds_a_system_binary()
    {

        $this->assertNotNull((new ImageOptimizer)->findBinary('head'));

    }

    public function test_it_returns_null_for_a_missing_binary()
    {

        $this->assertNull((new ImageOptimizer)->findBinary('definitely-not-installed-anywhere'));

    }

    public function test_it_knows_whether_a_binary_can_run()
    {

        $optimizer = new ImageOptimizer;

        $notExecutable = tempnam(sys_get_temp_dir(), 'imageoptimizer');
        chmod($notExecutable, 0644);

        $this->assertTrue($optimizer->canRun($optimizer->findBinary('head')));
        $this->assertFalse($optimizer->canRun($notExecutable));
        $this->assertFalse($optimizer->canRun('/definitely/not/here'));

        unlink($notExecutable);

    }

    public function test_it_only_falls_back_to_an_executable_bundled_binary()
    {

        $optimizer = new ImageOptimizer;

        $bundled = $optimizer->findBundledBinary('jpegoptim');

        if (!$bundled) {

            $this->markTestSkipped('No bundled binary for ' . PHP_OS);

        }

        $original = fileperms($bundled) & 0777;

        chmod($bundled, 0644);

        $this->assertTrue(is_executable((new ImageOptimizer)->findBinary('jpegoptim') ?? ''));

        chmod($bundled, $original);

    }

    public function test_every_bundled_binary_can_run_on_this_platform()
    {

        $optimizer = new ImageOptimizer;

        foreach (['jpegoptim', 'pngquant', 'optipng', 'gifsicle', 'cwebp'] as $name) {

            $bundled = $optimizer->findBundledBinary($name);

            if (!$bundled) {

                $this->markTestSkipped('No bundled binaries for ' . PHP_OS);

            }

            $this->assertTrue($optimizer->canRun($bundled), $name);

        }

    }

    public function test_it_picks_the_bundled_directory_per_platform()
    {

        $this->assertSame('linux-x86_64', ImageOptimizer::bundledDirectory('Linux', 'x86_64'));
        $this->assertSame('linux-x86_64', ImageOptimizer::bundledDirectory('Linux', 'amd64'));
        $this->assertSame('linux-aarch64', ImageOptimizer::bundledDirectory('Linux', 'aarch64'));
        $this->assertSame('linux-aarch64', ImageOptimizer::bundledDirectory('Linux', 'arm64'));
        $this->assertNull(ImageOptimizer::bundledDirectory('Linux', 'armv7l'));
        $this->assertNull(ImageOptimizer::bundledDirectory('Linux', 'i686'));
        $this->assertSame('darwin', ImageOptimizer::bundledDirectory('Darwin', 'arm64'));
        $this->assertSame('darwin', ImageOptimizer::bundledDirectory('Darwin', 'x86_64'));
        $this->assertSame('windows', ImageOptimizer::bundledDirectory('Windows', 'AMD64'));
        $this->assertNull(ImageOptimizer::bundledDirectory('BSD', 'amd64'));

    }

    public function test_the_bundled_directory_of_this_platform_exists()
    {

        $directory = ImageOptimizer::bundledDirectory(PHP_OS_FAMILY, php_uname('m'));

        if (!$directory) {

            $this->markTestSkipped('No bundled binaries for ' . PHP_OS . ' ' . php_uname('m'));

        }

        $this->assertDirectoryExists(__DIR__ . '/../../bin/' . $directory);

    }

    public function test_it_searches_the_configured_paths()
    {

        $dir = sys_get_temp_dir() . '/imageoptimizer-paths-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/imageoptimizer-probe', "#!/bin/sh\necho ok\n");
        chmod($dir . '/imageoptimizer-probe', 0755);

        $this->assertNull((new ImageOptimizer)->findBinary('imageoptimizer-probe'));

        Settings::save(['paths' => [$dir]]);

        $this->assertSame($dir . '/imageoptimizer-probe', (new ImageOptimizer)->findBinary('imageoptimizer-probe'));

        unlink($dir . '/imageoptimizer-probe');
        rmdir($dir);

    }

    public function test_a_binary_in_a_directory_with_a_space_runs()
    {

        $dir = sys_get_temp_dir() . '/image optimizer ' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/shrink', "#!/bin/sh\nhead -c 70 \"\$1\" > \"\$2\"\n");
        chmod($dir . '/shrink', 0755);

        Settings::save(['paths' => [$dir]]);
        $this->useOptimizer('shrink', ':file :temp');

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $this->assertSame(70, \Illuminate\Support\Facades\Storage::disk('test')->size('image.png'));

        unlink($dir . '/shrink');
        rmdir($dir);

    }

    public function test_it_reports_the_status_of_an_executable()
    {

        $optimizer = new ImageOptimizer;

        $this->assertSame('found', $optimizer->status('head')['status']);
        $this->assertNotNull($optimizer->status('head')['path']);
        $this->assertSame(['path' => null, 'status' => 'missing'], $optimizer->status('definitely-not-installed-anywhere'));

    }

}
