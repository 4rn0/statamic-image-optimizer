<?php

namespace Tests\Feature;

use Arnohoogma\StatamicImageOptimizer\Actions\DiscardOriginals;
use Arnohoogma\StatamicImageOptimizer\Actions\OptimizeImages;
use Arnohoogma\StatamicImageOptimizer\Actions\RevertImages;
use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Jobs\DiscardOriginalJob;
use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
use Arnohoogma\StatamicImageOptimizer\Jobs\RevertAssetJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Action;
use Statamic\Facades\Asset;
use Statamic\Facades\User;
use Tests\TestCase;

class ActionTest extends TestCase
{

    public function test_the_action_is_offered_for_images_only()
    {

        $this->actingAs(tap(User::make()->email('super@example.com')->makeSuper())->save());

        $image = $this->makeImage();

        Storage::disk('test')->put('doc.txt', 'hello');
        $doc = tap(\Statamic\Facades\AssetContainer::find('test')->makeAsset('doc.txt'))->save();

        $this->assertTrue(Action::for($image)->map->handle()->contains('optimize_images'));
        $this->assertFalse(Action::for($doc)->map->handle()->contains('optimize_images'));

        $this->assertFalse(Action::for($image, ['view' => 'form'])->map->handle()->contains('optimize_images'));
        $this->assertTrue(Action::forBulk(collect([$image, $this->makeImage('b.png')]))->map->handle()->contains('optimize_images'));

    }

    public function test_it_requires_permission_to_edit_the_asset()
    {

        $image = $this->makeImage();

        $this->assertTrue((new OptimizeImages)->authorize(tap(User::make()->makeSuper())->save(), $image));
        $this->assertFalse((new OptimizeImages)->authorize(tap(User::make()->email('x@example.com'))->save(), $image));

    }

    public function test_it_optimizes_inline_without_a_queue()
    {

        $image = $this->makeImage();

        $this->assertNull((new OptimizeImages)->run(collect([$image]), []));
        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_it_queues_jobs_with_a_queue()
    {

        config(['queue.default' => 'redis']);
        Queue::fake();

        $image = $this->makeImage();

        $this->assertSame(__('imageoptimizer::cp.queued'), (new OptimizeImages)->run(collect([$image]), []));

        Queue::assertPushed(OptimizeAssetJob::class, fn ($job) => $job->assetId === 'test::image.png');
        $this->assertSame(1070, Storage::disk('test')->size('image.png'));

    }

    public function test_revert_and_discard_are_offered_for_images_with_an_original_only()
    {

        $this->actingAs(tap(User::make()->email('super@example.com')->makeSuper())->save());

        $this->makeImage('a.png');
        $this->makeImage('b.png');

        (new ImageOptimizer)->optimizeAsset(Asset::find('test::a.png'));

        $handles = Action::for(Asset::find('test::a.png'))->map->handle();
        $this->assertTrue($handles->contains('revert_images'));
        $this->assertTrue($handles->contains('discard_originals'));

        $handles = Action::for(Asset::find('test::b.png'))->map->handle();
        $this->assertFalse($handles->contains('revert_images'));
        $this->assertFalse($handles->contains('discard_originals'));

    }

    public function test_revert_and_discard_require_permission_to_edit_the_asset()
    {

        $image = $this->makeImage();
        $nobody = tap(User::make()->email('x@example.com'))->save();

        $this->assertFalse((new RevertImages)->authorize($nobody, $image));
        $this->assertFalse((new DiscardOriginals)->authorize($nobody, $image));

    }

    public function test_revert_runs_inline_without_a_queue()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $result = (new RevertImages)->run(collect([Asset::find('test::image.png')]), []);

        $this->assertSame('imageOptimizer.reverted', $result['callback'][0]);
        $this->assertCount(2, $result['callback'][1]);

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));
        $this->assertNull(Asset::find('test::image.png')->get('imageoptimizer'));

    }

    public function test_discard_runs_inline_without_a_queue()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());
        $original = Asset::find('test::image.png')->get('imageoptimizer')['original'];

        $this->assertNull((new DiscardOriginals)->run(collect([Asset::find('test::image.png')]), []));

        $this->assertFalse(Storage::disk('test')->exists($original));
        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_revert_and_discard_queue_jobs_with_a_queue()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        config(['queue.default' => 'redis']);
        Queue::fake();

        $this->assertSame(__('imageoptimizer::cp.revert-queued'), (new RevertImages)->run(collect([Asset::find('test::image.png')]), []));
        $this->assertSame(__('imageoptimizer::cp.discard-queued'), (new DiscardOriginals)->run(collect([Asset::find('test::image.png')]), []));

        Queue::assertPushed(RevertAssetJob::class, fn ($job) => $job->assetId === 'test::image.png');
        Queue::assertPushed(DiscardOriginalJob::class, fn ($job) => $job->assetId === 'test::image.png');
        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

}
