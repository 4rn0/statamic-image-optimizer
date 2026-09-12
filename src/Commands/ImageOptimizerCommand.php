<?php

namespace Arnohoogma\StatamicImageOptimizer\Commands;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Statamic\Console\RunsInPlease;
use Illuminate\Console\Command;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;

class ImageOptimizerCommand extends Command
{

    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:optimize:images
        {--container=* : Only optimize the images in these asset containers}
        {--only-new : Skip images that have been optimized before}
        {--dry-run : List the images that would be optimized without touching them}
        {--no-clear : Leave the Glide cache alone afterwards}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize all your existing image Assets and clear the Glide cache.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {

        $assets = $this->assets();

        if ($this->option('dry-run')) {

            $assets->each(fn ($asset) => $this->line($asset->id()));
            $this->info($assets->count() . ' image Assets would be optimized.');

            return;

        }

        $optimizer = new ImageOptimizer();

        $this->output->progressStart( $assets->count() );

        foreach ($assets as $asset) {

            $optimizer->optimizeAsset($asset);
            $this->output->progressAdvance();

        }

        $this->output->progressFinish();

        $this->info('Your image Assets have been optimized.');

        if (!$this->option('no-clear')) {

    	    $this->call('statamic:glide:clear');

        }

    }

    /**
     * The image Assets to optimize, narrowed down by the command options
     *
     * @return \Illuminate\Support\Collection
     */
    private function assets()
    {

        $containers = $this->option('container');

        $assets = $containers
            ? collect($containers)->flatMap(fn ($handle) => AssetContainer::findOrFail($handle)->assets())
            : Asset::all();

        $assets = $assets->filter->isImage();

        if ($this->option('only-new')) {

            $assets = $assets->reject(fn ($asset) => $asset->get('imageoptimizer'));

        }

        return $assets->values();

    }

}
