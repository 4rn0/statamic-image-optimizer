<?php

namespace Arnohoogma\StatamicImageOptimizer\Commands;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Statamic\Console\RunsInPlease;
use Illuminate\Console\Command;
use Statamic\Assets\Asset;

class ImageOptimizerCommand extends Command
{

    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:optimize:images';

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

        $assets = Asset::all()->filter->isImage();
        $optimizer = new ImageOptimizer();

        $this->output->progressStart( $assets->count() );

        foreach ($assets as $asset) {

            $optimizer->optimizeAsset($asset);
            $this->output->progressAdvance();

        }

        $this->output->progressFinish();

        $this->info('Your image Assets have been optimized.');

    	$this->call('glide:clear');
        $this->call('cache:clear');

    }

}