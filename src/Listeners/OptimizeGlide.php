<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Events\GlideImageGenerated;

class OptimizeGlide implements ShouldQueue
{

    public function handle(GlideImageGenerated $event)
    {
        
        if (config('statamic.imageoptimizer.glide')) {

            $optimizer = new ImageOptimizer();
            $optimizer->optimizeGlide($event->path);

        }


    }
    
}
