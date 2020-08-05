<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Statamic\Events\AssetUploaded;

class OptimizeAsset
{

    public function handle(AssetUploaded $event)
    {

        $asset = $event->asset;

        if (!$asset->isImage())
        {
            
            return;
            
        }

        if (config('statamic.imageoptimizer.assets'))
        {
     	      
            $optimizer = new ImageOptimizer();
            $optimizer->optimizeAsset($asset);

        }

    }
    
}
