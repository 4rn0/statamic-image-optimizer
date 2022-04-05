<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Arnohoogma\StatamicImageOptimizer\Http\Controllers\ImageOptimizerController;
use Arnohoogma\StatamicImageOptimizer\Listeners\TransformAssetContainerBlueprint;
use Arnohoogma\StatamicImageOptimizer\Fieldtypes\ImageOptimizerFieldtype;
use Arnohoogma\StatamicImageOptimizer\Commands\ImageOptimizerCommand;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeAsset;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeGlide;
use Illuminate\Support\Facades\Artisan;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Events\AssetContainerBlueprintFound;
use Statamic\Events\GlideImageGenerated;
use Statamic\Events\AssetUploaded;
use Statamic\Facades\Utility;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{

	protected $publishAfterInstall = false;

    protected $scripts = [

        __DIR__ . '/../public/js/app.js'

    ];

    protected $listen = [

        AssetContainerBlueprintFound::class => [TransformAssetContainerBlueprint::class],
        GlideImageGenerated::class => [OptimizeGlide::class],
        AssetUploaded::class => [OptimizeAsset::class]

    ];

    protected $commands = [

        ImageOptimizerCommand::class

    ];

    protected $fieldtypes = [

        ImageOptimizerFieldtype::class

    ];

    public function boot()
    {

        parent::boot();

		$this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'statamic.imageoptimizer');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'imageoptimizer');
        $this->loadViewsFrom(__DIR__ . '/../resources/views/', 'imageoptimizer');

        if ($this->app->runningInConsole()) {

            $this->publishes([
				__DIR__ . '/../resources/lang' => resource_path('lang/vendor/imageoptimizer/')
			], 'imageoptimizer-lang');

            $this->publishes([
				__DIR__ . '/../config/config.php' => config_path('statamic/imageoptimizer.php')
			], 'imageoptimizer-config');

        }

		$this->createUtility();
		$this->publishAssets();

    }

	private function createUtility()
	{

		$utility = Utility::make('ImageOptimizer')->title('ImageOptimizer')->navTitle('Optimizer')->description( __('imageoptimizer::cp.description') )->icon('assets');

        $utility->routes(function($router) {

            $router->get('/', [ImageOptimizerController::class, 'index'])->name('index');
            $router->post('/{encoded_asset}', [ImageOptimizerController::class, 'optimize'])->name('optimize');

        });

        $utility->register();

	}

	private function publishAssets()
    {

		Statamic::afterInstalled(function($command) {

            Artisan::call('vendor:publish --tag=imageoptimizer-config');
            Artisan::call('vendor:publish --tag=imageoptimizer-lang');

        });

    }

}
