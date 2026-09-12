<?php

namespace Tests\Feature;

use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Asset;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Tests\TestCase;

class UtilityTest extends TestCase
{

    private function user(array $permissions)
    {

        Role::make('tester')->permissions(['access cp', ...$permissions])->save();

        return tap(User::make()->email('tester@example.com')->assignRole('tester'))->save();

    }

    private function super()
    {

        return tap(User::make()->email('super@example.com')->makeSuper())->save();

    }

    private function optimizeUrl($id, $query = '')
    {

        return cp_route('utilities.imageoptimizer.optimize', base64_encode($id)) . $query;

    }

    public function test_the_utility_page_shows_optimizers_and_statistics()
    {

        $this->makeImage();

        $this
            ->actingAs($this->super())
            ->get(cp_route('utilities.imageoptimizer.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('imageoptimizer::Utility')
                ->where('stats.images', ['test::image.png'])
                ->where('stats.optimized', [])
                ->where('optimizers.0.executable', 'head')
                ->where('optimizers.0.status', 'found')
                ->has('docsUrl')
                ->has('config.assets')
            );

    }

    public function test_it_requires_the_utility_permission()
    {

        $this->makeImage();

        $this
            ->actingAs($this->user(['edit test assets']))
            ->postJson($this->optimizeUrl('test::image.png'))
            ->assertForbidden();

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));

    }

    public function test_it_requires_permission_to_edit_the_asset()
    {

        $this->makeImage();

        $this
            ->actingAs($this->user(['access ImageOptimizer utility']))
            ->postJson($this->optimizeUrl('test::image.png'))
            ->assertForbidden();

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));

    }

    public function test_it_optimizes_an_asset()
    {

        $this->makeImage();

        $this
            ->actingAs($this->user(['access ImageOptimizer utility', 'edit test assets']))
            ->postJson($this->optimizeUrl('test::image.png'))
            ->assertOk()
            ->assertJsonPath('asset.data.values.imageoptimizer.original_size', 1070)
            ->assertJsonPath('asset.data.values.imageoptimizer.current_size', 70)
            ->assertJsonMissingPath('stats');

        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_it_returns_statistics_when_asked()
    {

        $this->makeImage();

        $this
            ->actingAs($this->super())
            ->postJson($this->optimizeUrl('test::image.png', '?statistics=1'))
            ->assertOk()
            ->assertJsonPath('stats.original_size', 1070)
            ->assertJsonPath('stats.current_size', 70)
            ->assertJsonPath('stats.optimized', ['test::image.png']);

    }

    public function test_unknown_and_non_image_assets_are_not_found()
    {

        Storage::disk('test')->put('doc.txt', 'hello');
        Asset::find('test::doc.txt') ?? tap(\Statamic\Facades\AssetContainer::find('test')->makeAsset('doc.txt'))->save();

        $this
            ->actingAs($this->super())
            ->postJson($this->optimizeUrl('test::missing.png'))
            ->assertNotFound();

        $this
            ->actingAs($this->super())
            ->postJson($this->optimizeUrl('test::doc.txt'))
            ->assertNotFound();

    }

    public function test_a_bulk_run_queues_one_job_per_image()
    {

        Queue::fake();

        $this->makeImage('a.png');
        $this->makeImage('b.png')->set('imageoptimizer', ['original_size' => 1, 'current_size' => 1])->save();

        $response = $this
            ->actingAs($this->super())
            ->postJson(cp_route('utilities.imageoptimizer.run'), ['only' => 'new'])
            ->assertOk()
            ->assertJsonPath('total', 1);

        Queue::assertPushed(OptimizeAssetJob::class, fn ($job) => $job->assetId === 'test::a.png' && $job->run === $response->json('run'));
        Queue::assertPushed(OptimizeAssetJob::class, 1);

        $this
            ->actingAs($this->super())
            ->getJson(cp_route('utilities.imageoptimizer.progress', $response->json('run')))
            ->assertOk()
            ->assertJsonPath('done', 0)
            ->assertJsonPath('total', 1)
            ->assertJsonMissingPath('stats');

    }

    public function test_a_bulk_run_only_includes_images_the_user_may_edit()
    {

        Queue::fake();

        Storage::fake('other');
        \Statamic\Facades\AssetContainer::make('other')->disk('other')->save();
        Storage::disk('other')->put('c.png', $this->png());
        \Statamic\Facades\AssetContainer::find('other')->makeAsset('c.png')->save();

        $this->makeImage('a.png');

        $this
            ->actingAs($this->user(['access ImageOptimizer utility', 'edit test assets']))
            ->postJson(cp_route('utilities.imageoptimizer.run'), ['only' => 'all'])
            ->assertOk()
            ->assertJsonPath('total', 1);

        Queue::assertPushed(OptimizeAssetJob::class, fn ($job) => $job->assetId === 'test::a.png');

    }

    public function test_a_finished_bulk_run_reports_statistics()
    {

        $this->makeImage('a.png');
        $this->makeImage('b.png');

        // sync queue: the jobs run during the request
        $run = $this
            ->actingAs($this->super())
            ->postJson(cp_route('utilities.imageoptimizer.run'), ['only' => 'all'])
            ->assertOk()
            ->json('run');

        $this
            ->actingAs($this->super())
            ->getJson(cp_route('utilities.imageoptimizer.progress', $run))
            ->assertOk()
            ->assertJsonPath('done', 2)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('stats.optimized', ['test::a.png', 'test::b.png'])
            ->assertJsonPath('stats.current_size', 140);

        $this->assertSame(70, Storage::disk('test')->size('a.png'));
        $this->assertTrue(Cache::has('imageoptimizer::run::' . $run . '::cleared'));

    }

    public function test_an_unknown_run_is_not_found()
    {

        $this
            ->actingAs($this->super())
            ->getJson(cp_route('utilities.imageoptimizer.progress', 'nope'))
            ->assertNotFound();

    }

    public function test_the_asset_editor_gets_the_fieldtype()
    {

        $this->makeImage();

        $response = $this
            ->actingAs($this->super())
            ->getJson(cp_route('assets.show', base64_encode('test::image.png')))
            ->assertOk()
            ->json('data.blueprint.tabs');

        $handles = collect($response)->pluck('sections')->flatten(1)->pluck('fields')->flatten(1)->pluck('handle');

        $this->assertContains('imageoptimizer', $handles);

    }

}
