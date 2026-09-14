<?php

namespace Arnohoogma\StatamicImageOptimizer\Http\Controllers;

use Statamic\Http\Resources\CP\Assets\Asset as AssetResource;
use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
use Arnohoogma\StatamicImageOptimizer\Report;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\CP\PublishForm;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Statamic\Facades\Asset;
use Statamic\Facades\Glide;
use Statamic\Facades\Utility;
use Inertia\Inertia;

class ImageOptimizerController extends CpController {

	public function index(Request $request)
	{

        $optimizer = new ImageOptimizer();

        $optimizers = collect(Settings::get('optimizers'))
            ->map(fn ($item) => $item + $optimizer->status($item['executable']))
            ->all();

        return Inertia::render('imageoptimizer::Utility', [

            'report' => Report::current(),
            'optimizers' => $optimizers,
            'docsUrl' => Utility::find('ImageOptimizer')->docsUrl(),
            'settings' => $this->settingsForm(),
            'queued' => $this->queued(),

        ]);

    }

    public function optimize(Request $request, $asset)
    {

        $asset = Asset::find(base64_decode($asset));

        abort_unless($asset && $asset->isImage(), 404);

        $this->authorize('edit', $asset);

        $optimizer = new ImageOptimizer();
    	$asset = $optimizer->optimizeAsset($asset);

        $response = ['asset' => new AssetResource($asset)];

        if ($request->has('report')) {

            $response['report'] = Report::build();

        }

        if ($request->has('clearcache')) {

            Glide::clearAsset($asset);

        }

    	return response()->json($response);

    }

    public function revert(Request $request, $asset)
    {

        $asset = Asset::find(base64_decode($asset));

        abort_unless($asset && $asset->isImage(), 404);

        $this->authorize('edit', $asset);

        $reverted = (new ImageOptimizer)->revertAsset($asset);

        return response()->json(['asset' => new AssetResource($asset), 'reverted' => $reverted]);

    }

    /**
     * The images a run would touch, for the request-per-image loop without a queue
     */
    public function images(Request $request)
    {

        return response()->json(['images' => $this->assets($request->input('only'))->map->id()->values()]);

    }

    /**
     * Queue a bulk run: one job per image, progress kept in the cache
     */
    public function run(Request $request)
    {

        $assets = $this->assets($request->input('only'));

        $run = Str::uuid()->toString();

        Cache::put('imageoptimizer::run::' . $run . '::total', $assets->count(), now()->addDay());
        Cache::put('imageoptimizer::run::' . $run . '::done', 0, now()->addDay());

        $assets->each(fn ($asset) => OptimizeAssetJob::dispatch($asset->id(), $run));

        return response()->json(['run' => $run, 'total' => $assets->count()]);

    }

    /**
     * Progress of a bulk run; clears the Glide cache and rebuilds the report once it is done
     */
    public function progress($run)
    {

        $total = Cache::get('imageoptimizer::run::' . $run . '::total');

        abort_if($total === null, 404);

        $done = min((int) Cache::get('imageoptimizer::run::' . $run . '::done', 0), $total);
        $failed = (int) Cache::get('imageoptimizer::run::' . $run . '::failed', 0);

        $response = ['run' => $run, 'total' => $total, 'done' => $done, 'failed' => $failed];

        if ($done >= $total) {

            if (Cache::add('imageoptimizer::run::' . $run . '::cleared', true, now()->addDay())) {

                Artisan::call('statamic:glide:clear');

                Report::build();

            }

            $response['report'] = Report::get();

        }

        return response()->json($response);

    }

    /**
     * One CSV row per image
     */
    public function export()
    {

        return response()->streamDownload(function () {

            $output = fopen('php://output', 'w');

            fputcsv($output, ['container', 'path', 'original_size', 'current_size', 'saved', 'percent', 'optimized_at', 'original_kept'], escape: '');

            foreach (Report::rows() as $row) {

                fputcsv($output, $row, escape: '');

            }

            fclose($output);

        }, 'imageoptimizer-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);

    }

    /**
     * The images the current user may optimize: all of them, or only the ones never optimized
     *
     * @param string|null $only
     * @return \Illuminate\Support\Collection
     */
    private function assets($only)
    {

        $assets = Asset::all()->filter->isImage();

        if ($only === 'new') {

            $assets = $assets->reject(fn ($asset) => $asset->get('imageoptimizer'));

        }

        return $assets->filter(fn ($asset) => User::current()->can('edit', $asset))->values();

    }

    /**
     * Validate and save the settings form
     */
    public function saveSettings(Request $request)
    {

        Settings::save(PublishForm::make(Settings::blueprint($this->canEditOptimizers()))->submit($request->all()));

        return response()->json(['saved' => true]);

    }

    /**
     * The settings form on the utility page: what Statamic's own settings page would render
     *
     * @return array
     */
    private function settingsForm()
    {

        $blueprint = Settings::blueprint($this->canEditOptimizers());
        $fields = $blueprint->fields()->addValues(collect(Settings::for())->only(Settings::EDITABLE)->all())->preProcess();

        return [
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $fields->values(),
            'meta' => $fields->meta(),
            'submitUrl' => cp_route('utilities.imageoptimizer.settings'),
        ];

    }

    private function canEditOptimizers()
    {

        $user = User::current();

        return $user->isSuper() || $user->hasPermission(Settings::PERMISSION);

    }

    private function queued()
    {

        return config('queue.default') !== 'sync';

    }

}
