<?php

namespace Tests\Feature;

use Arnohoogma\StatamicImageOptimizer\Actions\OptimizeImages;
use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Action;
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

}
