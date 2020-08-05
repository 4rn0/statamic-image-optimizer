<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Statamic\Events\GlideImageGenerated;

class OptimizeGlide
{

    public function handle(GlideImageGenerated $event)
    {
        
        if (config('statamic.imageoptimizer.glide'))
        {

            $optimizer = new ImageOptimizer();
            $optimizer->optimizeGlide($event->path);

        }


    }
    
}
