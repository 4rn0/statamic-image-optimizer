<?php

namespace Arnohoogma\StatamicImageOptimizer\Actions;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Jobs\RevertAssetJob;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;

class RevertImages extends Action
{

    protected $icon = 'history';

    public static function title()
    {
        return __('imageoptimizer::cp.revert-action');
    }

    public function confirmationText()
    {
        return 'imageoptimizer::cp.revert-confirm';
    }

    public function visibleTo($item)
    {
        return $item instanceof Asset && $item->isImage() && isset($item->get('imageoptimizer')['original']);
    }

    public function authorize($user, $item)
    {
        return $user->can('edit', $item);
    }

    public function run($items, $values)
    {

        if (config('queue.default') === 'sync') {

            $items->each(fn ($asset) => (new ImageOptimizer)->revertAsset($asset));

            // Same URLs, new bytes.
            return ['callback' => ['imageOptimizer.reverted', ImageOptimizer::urls($items)]];

        }

        $items->each(fn ($asset) => RevertAssetJob::dispatch($asset->id()));

        return __('imageoptimizer::cp.revert-queued');

    }

}
