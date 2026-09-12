<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Arnohoogma\StatamicImageOptimizer\Actions\OptimizeImages;
use Arnohoogma\StatamicImageOptimizer\Http\Controllers\ImageOptimizerController;
use Arnohoogma\StatamicImageOptimizer\Listeners\TransformAssetContainerBlueprint;
use Arnohoogma\StatamicImageOptimizer\Fieldtypes\ImageOptimizerFieldtype;
use Arnohoogma\StatamicImageOptimizer\Commands\ImageOptimizerCommand;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeAsset;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeGlide;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Events\AssetContainerBlueprintFound;
use Statamic\Events\GlideImageGenerated;
use Statamic\Events\AssetUploaded;
use Statamic\Events\AssetReuploaded;
use Statamic\Facades\Utility;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{

    // Config and translations are loaded manually below, under the `imageoptimizer` key/namespace.
    protected $config = false;
    protected $translations = false;

    protected $vite = [
        'input' => ['resources/js/addon.js'],
        'publicDirectory' => 'resources/dist',
    ];

    protected $listen = [

        AssetContainerBlueprintFound::class => [TransformAssetContainerBlueprint::class],
        GlideImageGenerated::class => [OptimizeGlide::class],
        AssetUploaded::class => [OptimizeAsset::class],
        AssetReuploaded::class => [OptimizeAsset::class]

    ];

    protected $actions = [

        OptimizeImages::class

    ];

    protected $commands = [

        ImageOptimizerCommand::class

    ];

    protected $fieldtypes = [

        ImageOptimizerFieldtype::class

    ];

    public function bootAddon()
    {

		$this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'statamic.imageoptimizer');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'imageoptimizer');

        $this->publishes([__DIR__ . '/../config/config.php' => config_path('statamic/imageoptimizer.php')], 'imageoptimizer-config');
        $this->publishes([__DIR__ . '/../resources/lang' => lang_path('vendor/imageoptimizer')], 'imageoptimizer-lang');

		$this->createUtility();
		$this->publishAssets();

    }

	private function createUtility()
	{

        Utility::extend(function() {

            $utility = Utility::register('ImageOptimizer')
                ->title('ImageOptimizer')
                ->navTitle('Optimizer')
                ->description( __('imageoptimizer::cp.description') )
                ->icon('assets')
                ->docsUrl('https://statamic.com/addons/4rn0/imageoptimizer/docs');

            $utility->routes(function($router) {

                $router->get('/', [ImageOptimizerController::class, 'index'])->name('index');
                $router->post('/run', [ImageOptimizerController::class, 'run'])->name('run');
                $router->get('/run/{run}', [ImageOptimizerController::class, 'progress'])->name('progress');
                $router->post('/{encoded_asset}', [ImageOptimizerController::class, 'optimize'])->name('optimize');

            });
        });

	}

	private function publishAssets()
    {

		Statamic::afterInstalled(function($command) {

            $command->call('vendor:publish', ['--tag' => 'imageoptimizer-config']);
            $command->call('vendor:publish', ['--tag' => 'imageoptimizer-lang']);

        });

    }

}
