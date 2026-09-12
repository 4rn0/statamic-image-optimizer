<?php

namespace Tests\Feature;

use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeAsset;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeGlide;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

        config(['statamic.imageoptimizer.assets' => false]);

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

        $this->assertSame(['original_size' => 1070, 'current_size' => 70], Asset::find('test::image.png')->get('imageoptimizer'));

    }

    public function test_a_glide_image_gets_optimized_when_generated()
    {

        Storage::disk('glide')->put('containers/test/image.png/abc/image.png', $this->png());

        GlideImageGenerated::dispatch('containers/test/image.png/abc/image.png', []);

        $this->assertSame(70, Storage::disk('glide')->size('containers/test/image.png/abc/image.png'));

    }

    public function test_glide_images_are_not_optimized_when_disabled()
    {

        config(['statamic.imageoptimizer.glide' => false]);

        Storage::disk('glide')->put('containers/test/image.png/abc/image.png', $this->png());

        GlideImageGenerated::dispatch('containers/test/image.png/abc/image.png', []);

        $this->assertSame(1070, Storage::disk('glide')->size('containers/test/image.png/abc/image.png'));

    }

}
