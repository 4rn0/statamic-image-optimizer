<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Glide;
use Tests\TestCase;

class OptimizeGlideTest extends TestCase
{

    const PATH = 'containers/test/image.png/abc123/image.png';

    public function test_it_optimizes_a_glide_image_on_the_cache_disk()
    {

        Storage::disk('glide')->put(static::PATH, $this->png());

        (new ImageOptimizer)->optimizeGlide(static::PATH);

        $this->assertSame(70, Glide::cacheDisk()->size(static::PATH));
        $this->assertTrue(Glide::cacheStore()->has('imageoptimizer::' . static::PATH));

    }

    public function test_it_only_optimizes_a_glide_image_once()
    {

        Storage::disk('glide')->put(static::PATH, $this->png());

        (new ImageOptimizer)->optimizeGlide(static::PATH);

        Storage::disk('glide')->put(static::PATH, $this->png());

        (new ImageOptimizer)->optimizeGlide(static::PATH);

        $this->assertSame(1070, Glide::cacheDisk()->size(static::PATH));

    }

    public function test_the_marker_is_forgotten_when_the_assets_glide_cache_is_cleared()
    {

        $asset = $this->makeImage();

        Storage::disk('glide')->put(static::PATH, $this->png());

        (new ImageOptimizer)->optimizeGlide(static::PATH);

        $this->assertContains('imageoptimizer::' . static::PATH, Glide::cacheStore()->get('asset::test::image.png'));

        Glide::clearAsset($asset);

        $this->assertFalse(Glide::cacheStore()->has('imageoptimizer::' . static::PATH));

    }

    public function test_path_based_glide_images_do_not_get_a_manifest()
    {

        Storage::disk('glide')->put('img/photo.png/abc123/photo.png', $this->png());

        (new ImageOptimizer)->optimizeGlide('img/photo.png/abc123/photo.png');

        $this->assertSame(70, Glide::cacheDisk()->size('img/photo.png/abc123/photo.png'));
        $this->assertTrue(Glide::cacheStore()->has('imageoptimizer::img/photo.png/abc123/photo.png'));
        $this->assertNull(Glide::cacheStore()->get('asset::img'));

    }

}
