<?php

namespace Tests;

use Arnohoogma\StatamicImageOptimizer\ServiceProvider;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{

    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    // A valid 70 byte 1x1 PNG followed by padding. Decoders ignore bytes after IEND, so an
    // "optimizer" that strips the padding produces a smaller image that is still valid.
    const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function getEnvironmentSetUp($app)
    {

        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('cache.stores.glide', ['driver' => 'array']);
        $app['config']->set('statamic.assets.image_manipulation.cache', 'glide');

    }

    protected function setUp(): void
    {

        parent::setUp();

        Storage::fake('test');
        Storage::fake('glide');

        AssetContainer::make('test')->disk('test')->save();

        $this->useOptimizer('head', '-c 70 :file > :temp');

    }

    protected function tearDown(): void
    {

        // Settings saved in a test land in resources/addons/ of the testbench skeleton
        $this->resetSettings();

        parent::tearDown();

    }

    /**
     * Forget everything saved through Settings::save(), including setUp's optimizer stub
     */
    protected function resetSettings()
    {

        Addon::get(Settings::PACKAGE)->settings()->delete();

    }

    protected function useOptimizer($executable, $arguments, $mimetype = 'image/png')
    {

        Settings::save(['optimizers' => [[
            'executable' => $executable,
            'arguments' => $arguments,
            'mimetype' => $mimetype,
        ]]]);

    }

    protected function png($padding = 1000)
    {

        return base64_decode(static::PNG) . str_repeat("\0", $padding);

    }

    protected function makeImage($path = 'image.png')
    {

        Storage::disk('test')->put($path, $this->png());

        return tap(AssetContainer::find('test')->makeAsset($path))->save();

    }

}
