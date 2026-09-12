<?php

namespace Arnohoogma\StatamicImageOptimizer\Actions;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;

class OptimizeImages extends Action
{

    protected $icon = 'assets';

    public static function title()
    {
        return __('imageoptimizer::cp.action');
    }

    public function visibleTo($item)
    {
        return $item instanceof Asset && $item->isImage();
    }

    public function authorize($user, $item)
    {
        return $user->can('edit', $item);
    }

    public function run($items, $values)
    {

        if (config('queue.default') === 'sync') {

            $items->each(fn ($asset) => (new ImageOptimizer)->optimizeAsset($asset));

            return;

        }

        $items->each(fn ($asset) => OptimizeAssetJob::dispatch($asset->id()));

        return __('imageoptimizer::cp.queued');

    }

}
