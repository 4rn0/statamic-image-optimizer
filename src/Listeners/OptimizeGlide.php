<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Events\GlideImageGenerated;

class OptimizeGlide implements ShouldQueue
{

    public function handle(GlideImageGenerated $event)
    {
        
        if (Settings::get('glide')) {

            $optimizer = new ImageOptimizer();
            $optimizer->optimizeGlide($event->path);

        }


    }
    
}
