<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Report;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Events\AssetUploaded;
use Statamic\Events\AssetReuploaded;

class OptimizeAsset implements ShouldQueue
{

    public function handle(AssetUploaded|AssetReuploaded $event)
    {

        $asset = $event->asset;

        if (!$asset->isImage()) {

            return;

        }

        if (!Settings::get('assets', $asset->container())) {

            Report::touch();

            return;

        }

        $optimizer = new ImageOptimizer();

        // A reupload is a new file: drop the old original and start the statistics over.
        if ($event instanceof AssetReuploaded) {

            $optimizer->deleteOriginal($asset);
            $asset->remove('imageoptimizer');

        }

        $optimizer->optimizeAsset($asset);

    }

}
