<?php

namespace Arnohoogma\StatamicImageOptimizer\Jobs;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Asset;

class OptimizeAssetJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param string $assetId
     * @param string|null $run Bulk run to report progress to
     */
    public function __construct(public string $assetId, public ?string $run = null)
    {
    }

    public function handle()
    {

        try {

            if ($asset = Asset::find($this->assetId)) {

                (new ImageOptimizer)->optimizeAsset($asset);

            }

        } finally {

            if ($this->run) {

                Cache::increment('imageoptimizer::run::' . $this->run . '::done');

            }

        }

    }

}
