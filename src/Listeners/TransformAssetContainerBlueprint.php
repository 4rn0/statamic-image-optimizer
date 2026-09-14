<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Statamic\Events\AssetContainerBlueprintFound;

class TransformAssetContainerBlueprint
{

    public function handle(AssetContainerBlueprintFound $event)
    {

    	if (request()->route()?->getName() === 'statamic.cp.assets.show' && $event->asset?->isImage())
        {

            // Computed, so saving the editor cannot write stale statistics back.
    		$event->blueprint->ensureField('imageoptimizer', [

    			'type' => 'image_optimizer',
                'visibility' => 'computed',
                'asset' => $event->asset->id(),

    		]);

        }

    }

}
