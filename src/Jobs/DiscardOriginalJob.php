<?php

namespace Arnohoogma\StatamicImageOptimizer\Jobs;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Statamic\Facades\Asset;

class DiscardOriginalJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param string $assetId
     */
    public function __construct(public string $assetId)
    {
    }

    public function handle()
    {

        if ($asset = Asset::find($this->assetId)) {

            (new ImageOptimizer)->discardOriginal($asset);

        }

    }

}
