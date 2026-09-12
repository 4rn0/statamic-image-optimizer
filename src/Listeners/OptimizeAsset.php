<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Events\AssetUploaded;
use Statamic\Events\AssetReuploaded;

class OptimizeAsset implements ShouldQueue
{

    public function handle(AssetUploaded|AssetReuploaded $event)
    {

        $asset = $event->asset;

        if (!$asset->isImage() || !config('statamic.imageoptimizer.assets')) {

            return;

        }

        // A reupload is a new file: start the statistics over.
        if ($event instanceof AssetReuploaded) {

            $asset->remove('imageoptimizer');

        }

        $optimizer = new ImageOptimizer();
        $optimizer->optimizeAsset($asset);

    }

}
