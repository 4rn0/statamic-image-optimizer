<?php

namespace Arnohoogma\StatamicImageOptimizer;

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
use Statamic\Facades\Utility;

class ServiceProvider extends AddonServiceProvider
{

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

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'imageoptimizer');
        $this->loadViewsFrom(__DIR__ . '/../resources/views/', 'imageoptimizer');

        if ($this->app->runningInConsole())
        {
            
            $this->publishes([__DIR__ . '/../resources/lang' => resource_path('lang/vendor/arnohoogma/statamic-image-optimizer/')], 'lang');
            $this->publishes([__DIR__ . '/../config/config.php' => config_path('statamic/imageoptimizer.php')], 'config');

        }

        $utility = Utility::make('ImageOptimizer')->title('ImageOptimizer')->navTitle('Optimizer')->description( __('imageoptimizer::cp.description') )->icon('assets');
        
        $utility->routes(function($router) {
            
            $router->get('/', [ImageOptimizerController::class, 'index'])->name('index');
            $router->post('/{encoded_asset}', [ImageOptimizerController::class, 'optimize'])->name('optimize');

        });

        $utility->register();

    }

}
