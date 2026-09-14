<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
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

    public function test_a_locked_asset_waits_for_the_lock()
    {

        $asset = $this->makeImage();

        // Held by another job for a second.
        Cache::lock('imageoptimizer::lock::test::image.png', 1)->get();

        $start = microtime(true);

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertGreaterThan(0.5, microtime(true) - $start);
        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_no_original_is_stored_until_the_file_gets_smaller()
    {

        // Doubles the file.
        $this->useOptimizer('cat', ':file :file > :temp');

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $data = Asset::find('test::image.png')->get('imageoptimizer');

        $this->assertNull($this->original());
        $this->assertSame([], array_values(array_filter(Storage::disk('test')->allFiles('.meta'), fn ($f) => str_contains($f, 'imageoptimizer-'))));
        $this->assertSame(1070, $data['original_size']);
        $this->assertSame(1070, $data['current_size']);

        $this->useOptimizer('head', '-c 70 :file > :temp');

        (new ImageOptimizer)->optimizeAsset(Asset::find('test::image.png'));

        $this->assertSame($this->png(), Storage::disk('test')->get($this->original()));
        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_files_without_an_optimizer_get_no_original()
    {

        Storage::disk('test')->put('image.avif', $bytes = random_bytes(300));
        $asset = tap(\Statamic\Facades\AssetContainer::find('test')->makeAsset('image.avif'))->save();

        (new ImageOptimizer)->optimizeAsset($asset);

        $this->assertNull($this->original('test::image.avif'));
        $this->assertSame($bytes, Storage::disk('test')->get('image.avif'));
        $this->assertSame(300, Asset::find('test::image.avif')->get('imageoptimizer')['current_size']);

    }

    public function test_a_job_run_twice_keeps_one_original_and_the_same_bytes()
    {

        $this->makeImage();

        (new OptimizeAssetJob('test::image.png'))->handle();
        $once = Storage::disk('test')->get('image.png');
        $original = $this->original();

        (new OptimizeAssetJob('test::image.png'))->handle();

        $this->assertSame($once, Storage::disk('test')->get('image.png'));
        $this->assertSame($original, $this->original());
        $this->assertCount(1, array_filter(Storage::disk('test')->allFiles('.meta'), fn ($f) => str_contains($f, 'imageoptimizer-')));

        (new OptimizeAssetJob('test::gone.png'))->handle();

    }


    public function test_an_edited_image_is_optimized_from_the_current_file_not_the_original()
    {

        // What ImageEditor leaves behind after an edit.
        $asset = $this->makeImage();
        $asset->set('imageoptimizer', ['original' => '.meta/imageoptimizer-x.png', 'edited' => true])->save();

        Storage::disk('test')->put('.meta/imageoptimizer-x.png', $this->png(5000));
        Storage::disk('test')->put('image.png', $edited = $this->png(200));

        (new ImageOptimizer)->optimizeAsset(Asset::find('test::image.png'));

        $data = Asset::find('test::image.png')->get('imageoptimizer');

        $this->assertSame(70, Storage::disk('test')->size('image.png'));
        $this->assertSame(strlen($edited), $data['original_size']);
        $this->assertSame(70, $data['current_size']);
        $this->assertTrue($data['edited']);
        $this->assertSame('.meta/imageoptimizer-x.png', $data['original']);
        $this->assertSame($this->png(5000), Storage::disk('test')->get('.meta/imageoptimizer-x.png'));

    }

    public function test_an_optimized_edit_is_not_optimized_again()
    {

        $asset = $this->makeImage();
        $asset->set('imageoptimizer', ['original' => '.meta/imageoptimizer-x.png', 'edited' => true, 'original_size' => 5000, 'current_size' => 200, 'optimized_at' => 1])->save();

        Storage::disk('test')->put('.meta/imageoptimizer-x.png', $this->png(5000));
        Storage::disk('test')->put('image.png', $edited = $this->png(200));

        (new ImageOptimizer)->optimizeAsset(Asset::find('test::image.png'));

        $this->assertSame($edited, Storage::disk('test')->get('image.png'));
        $this->assertSame(1, Asset::find('test::image.png')->get('imageoptimizer')['optimized_at']);

    }

    public function test_revert_refreshes_the_meta_of_an_edited_image()
    {

        $asset = $this->makeImage();
        $asset->set('imageoptimizer', ['original' => '.meta/imageoptimizer-x.png', 'edited' => true])->save();

        // A 2x2 PNG stands in for the edit; the kept original is the 1x1
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        Storage::disk('test')->put('image.png', ob_get_clean());
        Storage::disk('test')->put('.meta/imageoptimizer-x.png', $this->png());

        // What ImageEditor does after writing.
        $edited = Asset::find('test::image.png');
        $edited->cacheStore()->forget($edited->metaCacheKey());
        $edited->writeMeta($edited->generateMeta());

        $this->assertSame([2, 2], Asset::find('test::image.png')->dimensions());

        $this->assertTrue((new ImageOptimizer)->revertAsset(Asset::find('test::image.png')));

        $this->assertSame([1, 1], Asset::find('test::image.png')->dimensions());
        $this->assertSame(1070, Asset::find('test::image.png')->size());

    }

}
