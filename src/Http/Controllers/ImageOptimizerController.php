<?php

namespace Arnohoogma\StatamicImageOptimizer\Http\Controllers;

use Statamic\Http\Resources\CP\Assets\Asset as AssetResource;
use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Statamic\Assets\Asset;
use Inertia\Inertia;

class ImageOptimizerController extends CpController {

	public function index(Request $request)
	{

        $optimizers = config('statamic.imageoptimizer.optimizers');
        $optimizer = new ImageOptimizer();

        foreach ($optimizers as &$item) {

            $item['found'] = $optimizer->findBinary($item['executable']);

        }

        return Inertia::render('imageoptimizer::Utility', [

            'stats' => $this->getStatistics(),
            'optimizers' => $optimizers,
            'configPath' => config_path('statamic/imageoptimizer.php'),
            'config' => [
                'assets' => config('statamic.imageoptimizer.assets'),
                'glide' => config('statamic.imageoptimizer.glide'),
                'log' => config('statamic.imageoptimizer.log'),
            ],

        ]);

    }

    public function optimize(Request $request, $asset)
    {

        $optimizer = new ImageOptimizer();

        $asset = Asset::find(base64_decode($asset));
    	$asset = $optimizer->optimizeAsset($asset);

        $response = ['asset' => new AssetResource($asset)];

        if ($request->has('statistics')) {

            $response['stats'] = $this->getStatistics();

        }

        if ($request->has('clearcache')) {

            Artisan::call('statamic:glide:clear');
            Artisan::call('cache:clear');

        }

    	return response()->json($response);

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