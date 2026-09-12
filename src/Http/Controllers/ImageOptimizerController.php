<?php

namespace Arnohoogma\StatamicImageOptimizer\Http\Controllers;

use Statamic\Http\Resources\CP\Assets\Asset as AssetResource;
use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Statamic\Facades\Asset;
use Statamic\Facades\Utility;
use Inertia\Inertia;

class ImageOptimizerController extends CpController {

	public function index(Request $request)
	{

        $optimizers = config('statamic.imageoptimizer.optimizers');
        $optimizer = new ImageOptimizer();

        foreach ($optimizers as &$item) {

            $bundled = $optimizer->findBundledBinary($item['executable']);
            $path = $optimizer->findBinary($item['executable']) ?: $bundled;

            $item['path'] = $path ?: null;
            $item['status'] = match (true) {
                !$path => 'missing',
                !$optimizer->canRun($path) => 'broken',
                $path === $bundled => 'bundled',
                default => 'found',
            };

        }

        return Inertia::render('imageoptimizer::Utility', [

            'stats' => $this->getStatistics(),
            'optimizers' => $optimizers,
            'configPath' => config_path('statamic/imageoptimizer.php'),
            'docsUrl' => Utility::find('ImageOptimizer')->docsUrl(),
            'queued' => $this->queued(),
            'config' => [
                'assets' => config('statamic.imageoptimizer.assets'),
                'glide' => config('statamic.imageoptimizer.glide'),
                'log' => config('statamic.imageoptimizer.log'),
            ],

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

        if ($request->has('statistics')) {

            $response['stats'] = $this->getStatistics();

        }

        if ($request->has('clearcache')) {

            Artisan::call('statamic:glide:clear');

        }

    	return response()->json($response);

    }

    /**
     * Queue a bulk run: one job per image, progress kept in the cache
     */
    public function run(Request $request)
    {

        $assets = Asset::all()->filter->isImage();

        if ($request->input('only') === 'new') {

            $assets = $assets->reject(fn ($asset) => $asset->get('imageoptimizer'));

        }

        $assets = $assets->filter(fn ($asset) => User::current()->can('edit', $asset));

        $run = Str::uuid()->toString();

        Cache::put('imageoptimizer::run::' . $run . '::total', $assets->count(), now()->addDay());
        Cache::put('imageoptimizer::run::' . $run . '::done', 0, now()->addDay());

        $assets->each(fn ($asset) => OptimizeAssetJob::dispatch($asset->id(), $run));

        return response()->json(['run' => $run, 'total' => $assets->count()]);

    }

    /**
     * Progress of a bulk run; clears the Glide cache and returns statistics once it is done
     */
    public function progress($run)
    {

        $total = Cache::get('imageoptimizer::run::' . $run . '::total');

        abort_if($total === null, 404);

        $done = min((int) Cache::get('imageoptimizer::run::' . $run . '::done', 0), $total);

        $response = ['run' => $run, 'total' => $total, 'done' => $done];

        if ($done >= $total) {

            if (Cache::add('imageoptimizer::run::' . $run . '::cleared', true, now()->addDay())) {

                Artisan::call('statamic:glide:clear');

            }

            $response['stats'] = $this->getStatistics();

        }

        return response()->json($response);

    }

    private function queued()
    {

        return config('queue.default') !== 'sync';

    }

    private function getStatistics()
    {

        $assets = Asset::all()->filter->isImage();

        $optimized = collect();
        $original = collect();
        $current = collect();
        $images = collect();

        foreach ($assets as $asset)
        {

            if ($data = $asset->get('imageoptimizer')) {

                $original->push( $data['original_size'] ?? 0 );
                $current->push( $data['current_size'] ?? 0 );
                $optimized->push( $asset->id() );
                
            }

            $images->push( $asset->id() );

        }

        return [

            'original_size' => $original->sum(),
            'current_size' => $current->sum(),
            'optimized' => $optimized,
            'images' => $images

        ];

    }

}
