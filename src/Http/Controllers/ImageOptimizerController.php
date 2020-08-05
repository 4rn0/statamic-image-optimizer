<?php

namespace Arnohoogma\StatamicImageOptimizer\Http\Controllers;

use Statamic\Http\Resources\CP\Assets\Asset as AssetResource;
use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Statamic\Assets\Asset;

class ImageOptimizerController extends CpController {

	public function index(Request $request)
	{

        $optimizers = config('statamic.imageoptimizer.optimizers');
        $optimizer = new ImageOptimizer();

        foreach ($optimizers as &$item) {

            $item['found'] = $optimizer->findBinary($item['executable']);

        }

        return view('imageoptimizer::utility', [

            'stats' => $this->getStatistics(),
            'optimizers' => $optimizers

        ]);

    }

    public function optimize(Request $request, $asset_container, $asset)
    {

    	$optimizer = new ImageOptimizer();

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

            if ($data = $asset->get('imageoptimizer'))
            {

                $original->push( $data['original_size'] );
                $current->push( $data['current_size'] );
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