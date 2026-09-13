<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Statamic\Events\AssetContainerBlueprintFound;

class TransformAssetContainerBlueprint
{

    public function handle(AssetContainerBlueprintFound $event)
    {

    	if (request()->route()?->getName() === 'statamic.cp.assets.show' && $event->asset?->isImage())
        {

            // Computed: the editor shows the field but never saves it back, so saving the asset
            // after optimizing in the panel cannot write stale statistics over the fresh ones.
    		$event->blueprint->ensureField('imageoptimizer', [

    			'type' => 'image_optimizer',
                'visibility' => 'computed',
                'asset' => $event->asset->id(),

    		]);

        }

    }

}
