<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Statamic\Events\AssetUploaded;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetReplaced;

class OptimizeAsset
{

    public function handle(AssetUploaded|AssetReuploaded|AssetReplaced $event)
    {

        $asset = $event->newAsset ?? $event->asset;

        if (!$asset->isImage()) {
            
            return;
            
        }

        if (config('statamic.imageoptimizer.assets')) {
     	      
            $optimizer = new ImageOptimizer();
            $optimizer->optimizeAsset($asset);

        }

    }
    
}
