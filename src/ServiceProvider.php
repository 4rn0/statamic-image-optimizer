<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Arnohoogma\StatamicImageOptimizer\Actions\DiscardOriginals;
use Arnohoogma\StatamicImageOptimizer\Actions\OptimizeImages;
use Arnohoogma\StatamicImageOptimizer\Actions\RevertImages;
use Arnohoogma\StatamicImageOptimizer\Http\Controllers\ImageOptimizerController;
use Arnohoogma\StatamicImageOptimizer\Listeners\TransformAssetContainerBlueprint;
use Arnohoogma\StatamicImageOptimizer\Fieldtypes\ImageOptimizerExecutableFieldtype;
use Arnohoogma\StatamicImageOptimizer\Fieldtypes\ImageOptimizerFieldtype;
use Arnohoogma\StatamicImageOptimizer\Commands\ImageOptimizerCommand;
use Arnohoogma\StatamicImageOptimizer\Listeners\DeleteOriginal;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeAsset;
use Arnohoogma\StatamicImageOptimizer\Listeners\OptimizeGlide;
use Arnohoogma\StatamicImageOptimizer\UpdateScripts\ImportPublishedConfig;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Events\AssetContainerBlueprintFound;
use Statamic\Events\AssetDeleted;
use Statamic\Events\GlideImageGenerated;
use Statamic\Events\AssetUploaded;
use Statamic\Events\AssetReuploaded;
use Statamic\Facades\Permission;
use Statamic\Facades\Utility;

class ServiceProvider extends AddonServiceProvider
{

    // Settings live in the control panel; translations are loaded in bootAddon().
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
        AssetReuploaded::class => [OptimizeAsset::class],
        AssetDeleted::class => [DeleteOriginal::class]

    ];

    protected $actions = [

        OptimizeImages::class,
        RevertImages::class,
        DiscardOriginals::class

    ];

    protected $commands = [

        ImageOptimizerCommand::class

    ];

    protected $updateScripts = [

        ImportPublishedConfig::class

    ];

    protected $fieldtypes = [

        ImageOptimizerFieldtype::class,
        ImageOptimizerExecutableFieldtype::class

    ];

    public function bootAddon()
    {

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'imageoptimizer');
        $this->publishes([__DIR__ . '/../resources/lang' => lang_path('vendor/imageoptimizer')], 'imageoptimizer-lang');

		$this->createUtility();
		$this->registerPermission();

    }

    private function registerPermission()
    {

        Permission::extend(function () {

            Permission::group('utilities', __('statamic::permissions.group_utilities'), function () {

                Permission::register(Settings::PERMISSION)
                    ->label(__('imageoptimizer::cp.permission'))
                    ->description(__('imageoptimizer::cp.permission_description'));

            });

        });

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
                $router->get('/images', [ImageOptimizerController::class, 'images'])->name('images');
                $router->post('/run', [ImageOptimizerController::class, 'run'])->name('run');
                $router->get('/run/{run}', [ImageOptimizerController::class, 'progress'])->name('progress');
                $router->patch('/settings', [ImageOptimizerController::class, 'saveSettings'])->name('settings');
                $router->get('/report.csv', [ImageOptimizerController::class, 'export'])->name('export');
                $router->post('/{encoded_asset}', [ImageOptimizerController::class, 'optimize'])->name('optimize');
                $router->post('/{encoded_asset}/revert', [ImageOptimizerController::class, 'revert'])->name('revert');

            });
        });

	}


}
