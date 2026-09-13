<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Statamic\Events\GlideAssetCacheCleared;
use Statamic\Facades\Asset;
use Statamic\Facades\Glide;
use Tests\TestCase;

class OriginalsTest extends TestCase
{

    private function original($id = 'test::image.png')
    {

        return Asset::find($id)->get('imageoptimizer')['original'] ?? null;

    }

    public function test_the_original_is_stored_in_the_hidden_meta_folder()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $original = $this->original();

        $this->assertMatchesRegularExpression('#^\.meta/imageoptimizer-[0-9a-f-]{36}\.png$#', $original);
        $this->assertSame($this->png(), Storage::disk('test')->get($original));
        $this->assertSame(70, Storage::disk('test')->size('image.png'));

        // Hidden from the asset browser and from the Stache, but the meta file itself is still there
        $container = \Statamic\Facades\AssetContainer::find('test');

        $this->assertSame(['image.png'], $container->files()->all());
        $this->assertSame(['image.png'], $container->assets()->map->path()->all());
        $this->assertSame(['test::image.png'], Asset::all()->map->id()->all());
        $this->assertSame([], $container->assetFolders()->map->path()->all());
        $this->assertTrue(Asset::find('test::image.png')->exists());

    }

    public function test_the_original_is_never_overwritten()
    {

        $optimizer = new ImageOptimizer;

        $optimizer->optimizeAsset($this->makeImage());
        $original = $this->original();

        $optimizer->optimizeAsset(Asset::find('test::image.png'));

        $this->assertSame($original, $this->original());
        $this->assertSame($this->png(), Storage::disk('test')->get($original));
        $this->assertSame(1070, Asset::find('test::image.png')->get('imageoptimizer')['original_size']);

    }

    public function test_a_second_run_starts_from_the_original()
    {

        // Halves the file: optimizing the optimized file again would halve it again
        $this->useOptimizer('head', '-c 535 :file > :temp');

        $optimizer = new ImageOptimizer;

        $optimizer->optimizeAsset($this->makeImage());
        $optimizer->optimizeAsset(Asset::find('test::image.png'));

        $this->assertSame(535, Storage::disk('test')->size('image.png'));
        $this->assertSame(535, Asset::find('test::image.png')->get('imageoptimizer')['current_size']);

    }

    public function test_no_original_is_stored_for_images_optimized_before()
    {

        $asset = $this->makeImage();
        $asset->set('imageoptimizer', ['original_size' => 2000, 'current_size' => 1070])->save();

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertNull($this->original());
        $this->assertSame(70, Storage::disk('test')->size('image.png'));
        $this->assertSame(2000, Asset::find('test::image.png')->get('imageoptimizer')['original_size']);

    }

    public function test_no_original_is_stored_when_disabled()
    {

        Settings::save(['originals' => false]);

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $this->assertNull($this->original());
        $this->assertSame([], collect(Storage::disk('test')->allFiles('.meta'))->filter(fn ($f) => str_contains($f, 'imageoptimizer'))->all());

    }

    public function test_a_missing_original_is_forgotten_on_the_next_run()
    {

        $optimizer = new ImageOptimizer;

        $optimizer->optimizeAsset($this->makeImage());

        Storage::disk('test')->delete($this->original());

        $optimizer->optimizeAsset(Asset::find('test::image.png'));

        $this->assertNull($this->original());
        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_it_records_when_the_image_was_optimized()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $this->assertEqualsWithDelta(time(), Asset::find('test::image.png')->get('imageoptimizer')['optimized_at'], 5);

    }

    public function test_revert_restores_the_original_and_forgets_everything()
    {

        Event::fake([GlideAssetCacheCleared::class]);

        $optimizer = new ImageOptimizer;

        $optimizer->optimizeAsset($this->makeImage());
        $original = $this->original();

        $this->assertTrue($optimizer->revertAsset(Asset::find('test::image.png')));

        $this->assertSame($this->png(), Storage::disk('test')->get('image.png'));
        $this->assertFalse(Storage::disk('test')->exists($original));
        $this->assertNull(Asset::find('test::image.png')->get('imageoptimizer'));
        $this->assertSame(1070, Asset::find('test::image.png')->size());

        Event::assertDispatched(GlideAssetCacheCleared::class, fn ($event) => $event->asset->id() === 'test::image.png');

    }

    public function test_revert_clears_the_glide_markers_of_the_asset()
    {

        $optimizer = new ImageOptimizer;

        $optimizer->optimizeAsset($this->makeImage());

        Storage::disk('glide')->put('containers/test/image.png/abc/image.png', $this->png());
        $optimizer->optimizeGlide('containers/test/image.png/abc/image.png');

        $optimizer->revertAsset(Asset::find('test::image.png'));

        $this->assertFalse(Glide::cacheStore()->has('imageoptimizer::containers/test/image.png/abc/image.png'));

    }

    public function test_revert_does_nothing_without_an_original()
    {

        $asset = $this->makeImage();
        $asset->set('imageoptimizer', ['original_size' => 2000, 'current_size' => 1070])->save();

        $this->assertFalse((new ImageOptimizer)->revertAsset($asset));

        $this->assertSame($this->png(), Storage::disk('test')->get('image.png'));
        $this->assertSame(2000, Asset::find('test::image.png')->get('imageoptimizer')['original_size']);

    }

    public function test_revert_fails_cleanly_when_the_original_file_is_gone()
    {

        $optimizer = new ImageOptimizer;

        $optimizer->optimizeAsset($this->makeImage());

        Storage::disk('test')->delete($this->original());

        $this->assertFalse($optimizer->revertAsset(Asset::find('test::image.png')));

        $this->assertSame(70, Storage::disk('test')->size('image.png'));
        $this->assertNull($this->original());
        $this->assertSame(1070, Asset::find('test::image.png')->get('imageoptimizer')['original_size']);

    }

    public function test_discard_deletes_the_original_and_keeps_the_statistics()
    {

        $optimizer = new ImageOptimizer;

        $optimizer->optimizeAsset($this->makeImage());
        $original = $this->original();

        $optimizer->discardOriginal(Asset::find('test::image.png'));

        $this->assertFalse(Storage::disk('test')->exists($original));
        $this->assertNull($this->original());
        $this->assertSame(1070, Asset::find('test::image.png')->get('imageoptimizer')['original_size']);
        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_a_locked_asset_is_skipped()
    {

        $asset = $this->makeImage();

        Cache::lock('imageoptimizer::lock::test::image.png', 10)->get();

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));
        $this->assertNull(Asset::find('test::image.png')->get('imageoptimizer'));

    }

}
