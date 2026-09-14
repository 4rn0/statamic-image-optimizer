<?php

namespace Tests\Feature;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
use Arnohoogma\StatamicImageOptimizer\Report;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Statamic\Events\GlideAssetCacheCleared;
use Illuminate\Support\Facades\Event;
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
                ->where('report.totals.images', 1)
                ->where('report.totals.optimized', 0)
                ->where('optimizers.0.executable', 'head')
                ->where('optimizers.0.status', 'found')
                ->has('docsUrl')
                ->where('settings.submitUrl', cp_route('utilities.imageoptimizer.settings'))
                ->where('settings.values.log', false)
                ->where('settings.values.optimizers.0.executable', 'head')
                ->has('settings.blueprint.tabs.0.sections', 2)
                ->has('settings.meta')
            );

    }

    public function test_the_optimizers_section_needs_its_permission()
    {

        $this
            ->actingAs($this->user(['access ImageOptimizer utility']))
            ->get(cp_route('utilities.imageoptimizer.index'))
            ->assertInertia(fn ($page) => $page
                ->has('settings.blueprint.tabs.0.sections', 1)
                ->missing('settings.values.optimizers')
                ->where('optimizers.0.executable', 'head')
            );

    }

    public function test_the_embedded_settings_form_shows_saved_values()
    {

        \Statamic\Facades\Addon::get(\Arnohoogma\StatamicImageOptimizer\Settings::PACKAGE)->settings()->set(['log' => true, 'assets' => false])->save();

        $this
            ->actingAs($this->super())
            ->get(cp_route('utilities.imageoptimizer.index'))
            ->assertInertia(fn ($page) => $page
                ->where('settings.values.log', true)
                ->where('settings.values.assets', false)
                ->where('settings.values.glide', true)
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

    public function test_it_clears_the_glide_cache_of_that_asset_when_asked()
    {

        Event::fake([GlideAssetCacheCleared::class]);

        $this->makeImage();

        $this
            ->actingAs($this->super())
            ->postJson($this->optimizeUrl('test::image.png', '?clearcache=1'))
            ->assertOk();

        Event::assertDispatched(GlideAssetCacheCleared::class, fn ($event) => $event->asset->id() === 'test::image.png');

    }

    public function test_it_reverts_an_asset()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $this
            ->actingAs($this->user(['access ImageOptimizer utility', 'edit test assets']))
            ->postJson(cp_route('utilities.imageoptimizer.revert', base64_encode('test::image.png')))
            ->assertOk()
            ->assertJsonPath('reverted', true)
            ->assertJsonPath('asset.data.values.imageoptimizer', null);

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));

    }

    public function test_reverting_needs_permission_to_edit_the_asset()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $this
            ->actingAs($this->user(['access ImageOptimizer utility']))
            ->postJson(cp_route('utilities.imageoptimizer.revert', base64_encode('test::image.png')))
            ->assertForbidden();

        $this->assertSame(70, Storage::disk('test')->size('image.png'));

    }

    public function test_reverting_without_an_original_reports_it()
    {

        $this->makeImage()->set('imageoptimizer', ['original_size' => 2000, 'current_size' => 1070])->save();

        $this
            ->actingAs($this->super())
            ->postJson(cp_route('utilities.imageoptimizer.revert', base64_encode('test::image.png')))
            ->assertOk()
            ->assertJsonPath('reverted', false)
            ->assertJsonPath('asset.data.values.imageoptimizer.original_size', 2000);

    }

    public function test_it_rebuilds_the_report_when_asked()
    {

        $this->makeImage();

        $this
            ->actingAs($this->super())
            ->postJson($this->optimizeUrl('test::image.png', '?report=1'))
            ->assertOk()
            ->assertJsonPath('report.totals.original_size', 1070)
            ->assertJsonPath('report.totals.current_size', 70)
            ->assertJsonPath('report.totals.optimized', 1);

        $this->assertSame(1, Report::get()['totals']['optimized']);

    }

    public function test_the_utility_page_shows_the_last_report()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());
        Report::build();

        $this
            ->actingAs($this->super())
            ->get(cp_route('utilities.imageoptimizer.index'))
            ->assertInertia(fn ($page) => $page
                ->where('report.totals.images', 1)
                ->where('report.containers.0.handle', 'test')
            );

    }

    public function test_the_image_list_respects_only_new_and_permissions()
    {

        Storage::fake('other');
        \Statamic\Facades\AssetContainer::make('other')->disk('other')->save();
        Storage::disk('other')->put('c.png', $this->png());
        \Statamic\Facades\AssetContainer::find('other')->makeAsset('c.png')->save();

        $this->makeImage('a.png');
        $this->makeImage('b.png')->set('imageoptimizer', ['original_size' => 1, 'current_size' => 1])->save();

        $user = $this->user(['access ImageOptimizer utility', 'edit test assets']);

        $this
            ->actingAs($user)
            ->getJson(cp_route('utilities.imageoptimizer.images', ['only' => 'all']))
            ->assertOk()
            ->assertJsonPath('images', ['test::a.png', 'test::b.png']);

        $this
            ->actingAs($user)
            ->getJson(cp_route('utilities.imageoptimizer.images', ['only' => 'new']))
            ->assertOk()
            ->assertJsonPath('images', ['test::a.png']);

    }

    public function test_the_utility_page_rebuilds_a_stale_report_only()
    {

        $this->makeImage('a.png');
        Report::build();

        $this->makeImage('b.png');

        // Nothing marked the report stale: the stored one is shown
        $this
            ->actingAs($this->super())
            ->get(cp_route('utilities.imageoptimizer.index'))
            ->assertInertia(fn ($page) => $page->where('report.totals.images', 1));

        (new ImageOptimizer)->optimizeAsset(Asset::find('test::b.png'));

        $this
            ->actingAs($this->super())
            ->get(cp_route('utilities.imageoptimizer.index'))
            ->assertInertia(fn ($page) => $page
                ->where('report.totals.images', 2)
                ->where('report.totals.optimized', 1)
            );

    }

    public function test_the_report_can_be_exported_as_csv()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage('a.png'));
        $this->makeImage('b.png');

        $response = $this
            ->actingAs($this->user(['access ImageOptimizer utility']))
            ->get(cp_route('utilities.imageoptimizer.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $lines = explode("\n", trim($response->streamedContent()));

        $this->assertSame('container,path,original_size,current_size,saved,percent,optimized_at,original_kept', $lines[0]);
        $this->assertStringStartsWith('test,a.png,1070,70,1000,93.46,', $lines[1]);
        $this->assertStringEndsWith(',yes', $lines[1]);
        $this->assertSame('test,b.png,,,,,,no', $lines[2]);

        $this
            ->actingAs($this->user([]))
            ->getJson(cp_route('utilities.imageoptimizer.export'))
            ->assertForbidden();

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
            ->assertJsonMissingPath('report');

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

    public function test_a_finished_bulk_run_rebuilds_the_report()
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
            ->assertJsonPath('report.totals.optimized', 2)
            ->assertJsonPath('report.totals.current_size', 140);

        $this->assertSame(70, Storage::disk('test')->size('a.png'));
        $this->assertTrue(Cache::has('imageoptimizer::run::' . $run . '::cleared'));

    }

    public function test_a_bulk_run_counts_the_images_that_failed()
    {

        $this->makeImage('a.png');
        $this->makeImage('b.png');

        $run = $this
            ->actingAs($this->super())
            ->postJson(cp_route('utilities.imageoptimizer.run'), ['only' => 'all'])
            ->json('run');

        // What a job does when the optimization throws
        Cache::increment('imageoptimizer::run::' . $run . '::failed');

        $this
            ->actingAs($this->super())
            ->getJson(cp_route('utilities.imageoptimizer.progress', $run))
            ->assertOk()
            ->assertJsonPath('done', 2)
            ->assertJsonPath('failed', 1);

    }

    public function test_a_failing_job_is_counted_and_fails()
    {

        Storage::disk('test')->put('image.png', 'not an image');
        \Statamic\Facades\AssetContainer::find('test')->makeAsset('image.png')->save();

        $disk = Storage::disk('test');
        $failing = \Mockery::mock($disk)->makePartial();
        $failing->shouldReceive('readStream')->andThrow(new \RuntimeException('disk down'));
        Storage::set('test', $failing);

        try {

            (new OptimizeAssetJob('test::image.png', 'run-1'))->handle();
            $this->fail('The job should fail');

        } catch (\RuntimeException $e) {

            $this->assertSame(1, Cache::get('imageoptimizer::run::run-1::failed'));
            $this->assertSame(1, Cache::get('imageoptimizer::run::run-1::done'));

        }

        Storage::set('test', $disk);

    }

    public function test_asset_ids_with_unicode_and_folders_are_decoded()
    {

        Storage::disk('test')->put('map/ünï 日本.png', $this->png());
        \Statamic\Facades\AssetContainer::find('test')->makeAsset('map/ünï 日本.png')->save();

        $this
            ->actingAs($this->super())
            ->postJson($this->optimizeUrl('test::map/ünï 日本.png'))
            ->assertOk()
            ->assertJsonPath('asset.data.values.imageoptimizer.current_size', 70);

    }

    public function test_the_csv_quotes_paths_with_commas_and_quotes()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage('com,ma "quote".png'));

        $lines = explode("\n", trim($this->actingAs($this->super())->get(cp_route('utilities.imageoptimizer.export'))->streamedContent()));

        $this->assertStringStartsWith('test,"com,ma ""quote"".png",1070,70,', $lines[1]);
        $this->assertSame('com,ma "quote".png', str_getcsv($lines[1])[1]);

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

        $field = collect($response)->pluck('sections')->flatten(1)->pluck('fields')->flatten(1)->firstWhere('handle', 'imageoptimizer');

        $this->assertSame('image_optimizer', $field['type']);
        $this->assertSame('computed', $field['visibility']);
        $this->assertSame('test::image.png', $field['asset']);

    }

    public function test_the_asset_editor_of_a_non_image_has_no_fieldtype()
    {

        Storage::disk('test')->put('doc.txt', 'hello');
        tap(\Statamic\Facades\AssetContainer::find('test')->makeAsset('doc.txt'))->save();

        $response = $this
            ->actingAs($this->super())
            ->getJson(cp_route('assets.show', base64_encode('test::doc.txt')))
            ->assertOk()
            ->json('data.blueprint.tabs');

        $handles = collect($response)->pluck('sections')->flatten(1)->pluck('fields')->flatten(1)->pluck('handle');

        $this->assertNotContains('imageoptimizer', $handles);

    }

    public function test_saving_the_asset_editor_does_not_write_stale_statistics()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage());

        $this
            ->actingAs($this->super())
            ->patchJson(cp_route('assets.update', base64_encode('test::image.png')), [
                'alt' => 'hello',
                'imageoptimizer' => ['original_size' => 1, 'current_size' => 1],
            ])
            ->assertOk();

        $this->assertSame(1070, Asset::find('test::image.png')->get('imageoptimizer')['original_size']);
        $this->assertSame('hello', Asset::find('test::image.png')->get('alt'));

    }

}
