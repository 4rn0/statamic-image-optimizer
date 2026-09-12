<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
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

}
