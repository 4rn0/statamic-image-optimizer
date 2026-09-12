<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
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

        $this->assertSame(['original_size' => 1070, 'current_size' => 70], Asset::find('test::image.png')->get('imageoptimizer'));

    }

    public function test_it_leaves_the_file_alone_when_the_optimizer_makes_it_bigger()
    {

        $this->useOptimizer('cat', ':file :file > :temp');

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));
        $this->assertSame(['original_size' => 1070, 'current_size' => 1070], Asset::find('test::image.png')->get('imageoptimizer'));

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

        config(['statamic.imageoptimizer.optimizers' => [
            ['executable' => 'head', 'arguments' => '-c 70 :file > :temp', 'mimetype' => 'image/png'],
            ['executable' => 'head', 'arguments' => '-c 10 :file > :temp', 'mimetype' => 'image/gif'],
        ]]);

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

}
