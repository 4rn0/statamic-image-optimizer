<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Asset;
use Tests\TestCase;

class OptimizeAssetTest extends TestCase
{

    public function test_it_optimizes_an_asset_and_stores_sizes()
    {

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);

        $asset = Asset::find('test::image.png');

        $this->assertSame(1070, $asset->get('imageoptimizer')['original_size']);
        $this->assertSame(70, $asset->get('imageoptimizer')['current_size']);
        $this->assertSame(70, Storage::disk('test')->size('image.png'));
        $this->assertSame(70, $asset->size());

    }

    public function test_it_keeps_the_original_size_on_a_second_run()
    {

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);
        (new ImageOptimizer)->optimizeAsset(Asset::find('test::image.png'));

        $data = Asset::find('test::image.png')->get('imageoptimizer');

        $this->assertSame(1070, $data['original_size']);
        $this->assertSame(70, $data['current_size']);

    }

    public function test_it_leaves_the_file_alone_when_the_optimizer_makes_it_bigger()
    {

        $this->useOptimizer('cat', ':file :file > :temp');

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));
        $this->assertSame(1070, Asset::find('test::image.png')->get('imageoptimizer')['original_size']);
        $this->assertSame(1070, Asset::find('test::image.png')->get('imageoptimizer')['current_size']);

    }

    public function test_it_leaves_the_file_alone_when_the_optimizer_fails()
    {

        $this->useOptimizer('false', ':file');

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertSame($this->png(), Storage::disk('test')->get('image.png'));

    }

    public function test_it_skips_optimizers_whose_binary_is_missing()
    {

        $this->useOptimizer('definitely-not-installed-anywhere', ':file');

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertSame($this->png(), Storage::disk('test')->get('image.png'));

    }

    public function test_it_skips_optimizers_for_other_mimetypes()
    {

        $this->useOptimizer('head', '-c 70 :file > :temp', 'image/jpeg');

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));

    }

    public function test_it_cleans_up_temp_files()
    {

        $before = count(glob(sys_get_temp_dir() . '/imageoptimizer*'));

        $this->useOptimizer('false', ':file > :temp');
        (new ImageOptimizer)->optimizeAsset($this->makeImage('a.png'));

        $this->useOptimizer('head', '-c 70 :file > :temp');
        (new ImageOptimizer)->optimizeAsset($this->makeImage('b.png'));

        $this->assertSame($before, count(glob(sys_get_temp_dir() . '/imageoptimizer*')));

    }

    public function test_it_only_runs_the_optimizer_of_a_matching_mimetype()
    {

        $log = [];

        Settings::save(['optimizers' => [
            ['executable' => 'head', 'arguments' => '-c 70 :file > :temp', 'mimetype' => 'image/png'],
            ['executable' => 'head', 'arguments' => '-c 10 :file > :temp', 'mimetype' => 'image/gif'],
        ]]);

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    /**
     * A WebP container with one chunk: lossy (VP8 ), lossless (VP8L) or extended (VP8X) with flags
     */
    private function webp($fourcc, $flags = 0)
    {

        return 'RIFF' . pack('V', 118) . 'WEBP' . $fourcc . pack('V', 10) . chr($flags) . str_repeat("\0", 99);

    }

    public function test_it_optimizes_lossy_webp_only()
    {

        $this->useOptimizer('head', '-c 20 :file > :temp', 'image/webp');

        Storage::disk('test')->put('lossy.webp', $this->webp('VP8 '));
        Storage::disk('test')->put('lossless.webp', $this->webp('VP8L'));
        Storage::disk('test')->put('animated.webp', $this->webp('VP8X', 0x02));
        Storage::disk('test')->put('extended.webp', $this->webp('VP8X', 0x10));

        foreach (['lossy', 'lossless', 'animated', 'extended'] as $name) {

            (new ImageOptimizer)->optimizeAsset(tap(\Statamic\Facades\AssetContainer::find('test')->makeAsset($name . '.webp'))->save());

        }

        $this->assertSame(20, Storage::disk('test')->size('lossy.webp'));
        $this->assertSame(120, Storage::disk('test')->size('lossless.webp'));
        $this->assertSame(120, Storage::disk('test')->size('animated.webp'));
        $this->assertSame(20, Storage::disk('test')->size('extended.webp'));

    }

    public function test_the_webp_sniff_finds_lossless_data_behind_other_chunks()
    {

        $optimizer = new ImageOptimizer;

        // VP8X (no animation), an odd-sized ICCP chunk with padding, then VP8L
        $file = 'RIFF' . pack('V', 0) . 'WEBP'
            . 'VP8X' . pack('V', 10) . chr(0x20) . str_repeat("\0", 9)
            . 'ICCP' . pack('V', 3) . 'abc' . "\0"
            . 'VP8L' . pack('V', 5) . 'hello' . "\0";

        $path = tempnam(sys_get_temp_dir(), 'imageoptimizer');
        file_put_contents($path, $file);

        $this->assertTrue($optimizer->isLosslessOrAnimatedWebp($path));

        file_put_contents($path, str_replace('VP8L', 'VP8 ', $file));

        $this->assertFalse($optimizer->isLosslessOrAnimatedWebp($path));

        file_put_contents($path, $this->png());

        $this->assertFalse($optimizer->isLosslessOrAnimatedWebp($path));

        unlink($path);

    }

}
