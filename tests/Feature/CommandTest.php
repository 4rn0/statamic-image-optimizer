<?php

namespace Tests\Feature;

use Arnohoogma\StatamicImageOptimizer\Report;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

class CommandTest extends TestCase
{

    public function test_it_optimizes_all_images()
    {

        $this->makeImage('a.png');
        $this->makeImage('b.png');

        $this->artisan('statamic:optimize:images', ['--no-clear' => true])->assertSuccessful();

        $this->assertSame(70, Storage::disk('test')->size('a.png'));
        $this->assertSame(70, Storage::disk('test')->size('b.png'));
        $this->assertSame(70, Asset::find('test::a.png')->get('imageoptimizer')['current_size']);
        $this->assertSame(2, Report::get()['totals']['optimized']);

    }

    public function test_dry_run_lists_images_without_touching_them()
    {

        $this->makeImage('a.png');

        $this->artisan('statamic:optimize:images', ['--dry-run' => true])
            ->expectsOutput('test::a.png')
            ->expectsOutput('1 image Assets would be optimized.')
            ->assertSuccessful();

        $this->assertSame(1070, Storage::disk('test')->size('a.png'));

    }

    public function test_only_new_skips_optimized_images()
    {

        $this->makeImage('a.png')->set('imageoptimizer', ['original_size' => 1, 'current_size' => 1])->save();
        $this->makeImage('b.png');

        $this->artisan('statamic:optimize:images', ['--only-new' => true, '--dry-run' => true])
            ->doesntExpectOutput('test::a.png')
            ->expectsOutput('test::b.png')
            ->assertSuccessful();

        $this->assertNull(Report::get());

    }

    public function test_container_option_limits_the_run()
    {

        Storage::fake('other');
        AssetContainer::make('other')->disk('other')->save();
        Storage::disk('other')->put('c.png', $this->png());
        AssetContainer::find('other')->makeAsset('c.png')->save();

        $this->makeImage('a.png');

        $this->artisan('statamic:optimize:images', ['--container' => ['other'], '--no-clear' => true])->assertSuccessful();

        $this->assertSame(70, Storage::disk('other')->size('c.png'));
        $this->assertSame(1070, Storage::disk('test')->size('a.png'));

    }

}
