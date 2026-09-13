<?php

namespace Tests\Feature;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Arnohoogma\StatamicImageOptimizer\Listeners\DeleteOriginal;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeAsset;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeGlide;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Statamic\Events\AssetDeleted;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetUploaded;
use Statamic\Events\GlideImageGenerated;
use Statamic\Facades\Asset;
use Tests\TestCase;

class ListenersTest extends TestCase
{

    public function test_listeners_are_registered()
    {

        Event::fake();

        Event::assertListening(AssetUploaded::class, OptimizeAsset::class);
        Event::assertListening(AssetReuploaded::class, OptimizeAsset::class);
        Event::assertListening(GlideImageGenerated::class, OptimizeGlide::class);
        Event::assertListening(AssetDeleted::class, DeleteOriginal::class);

    }

    public function test_listeners_run_on_the_queue()
    {

        Queue::fake();

        AssetUploaded::dispatch($this->makeImage(), 'image.png');
        GlideImageGenerated::dispatch('containers/test/image.png/abc/image.png', []);

        Queue::assertPushed(CallQueuedListener::class, fn ($job) => $job->class === OptimizeAsset::class);
        Queue::assertPushed(CallQueuedListener::class, fn ($job) => $job->class === OptimizeGlide::class);

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));

    }

    public function test_an_upload_gets_optimized()
    {

        $asset = $this->makeImage();

        AssetUploaded::dispatch($asset, 'image.png');

        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_uploads_are_not_optimized_when_disabled()
    {

        Settings::save(['assets' => false]);

        $asset = $this->makeImage();

        AssetUploaded::dispatch($asset, 'image.png');

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));
        $this->assertNull(Asset::find('test::image.png')->get('imageoptimizer'));

    }

    public function test_a_reupload_starts_the_statistics_over()
    {

        $asset = $this->makeImage();
        $asset->set('imageoptimizer', ['original_size' => 999999, 'current_size' => 500000])->save();

        AssetReuploaded::dispatch($asset, 'image.png');

        $data = Asset::find('test::image.png')->get('imageoptimizer');

        $this->assertSame(1070, $data['original_size']);
        $this->assertSame(70, $data['current_size']);

    }

    public function test_a_reupload_replaces_the_stored_original()
    {

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);
        $old = Asset::find('test::image.png')->get('imageoptimizer')['original'];

        // The new file Statamic wrote over the asset
        Storage::disk('test')->put('image.png', $this->png(2000));

        AssetReuploaded::dispatch(Asset::find('test::image.png'), 'image.png');

        $new = Asset::find('test::image.png')->get('imageoptimizer')['original'];

        $this->assertNotSame($old, $new);
        $this->assertFalse(Storage::disk('test')->exists($old));
        $this->assertSame($this->png(2000), Storage::disk('test')->get($new));
        $this->assertSame(2070, Asset::find('test::image.png')->get('imageoptimizer')['original_size']);

    }

    public function test_deleting_an_asset_deletes_its_original()
    {

        $asset = $this->makeImage();

        (new ImageOptimizer)->optimizeAsset($asset);
        $original = Asset::find('test::image.png')->get('imageoptimizer')['original'];

        Asset::find('test::image.png')->delete();

        $this->assertFalse(Storage::disk('test')->exists($original));
        $this->assertFalse(Storage::disk('test')->exists('image.png'));

    }

    public function test_a_glide_image_gets_optimized_when_generated()
    {

        Storage::disk('glide')->put('containers/test/image.png/abc/image.png', $this->png());

        GlideImageGenerated::dispatch('containers/test/image.png/abc/image.png', []);

        $this->assertSame(70, Storage::disk('glide')->size('containers/test/image.png/abc/image.png'));

    }

    public function test_glide_images_are_not_optimized_when_disabled()
    {

        Settings::save(['glide' => false]);

        Storage::disk('glide')->put('containers/test/image.png/abc/image.png', $this->png());

        GlideImageGenerated::dispatch('containers/test/image.png/abc/image.png', []);

        $this->assertSame(1070, Storage::disk('glide')->size('containers/test/image.png/abc/image.png'));

    }

}
