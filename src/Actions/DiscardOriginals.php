<?php

namespace Arnohoogma\StatamicImageOptimizer\Actions;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Jobs\DiscardOriginalJob;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;

class DiscardOriginals extends Action
{

    protected $icon = 'trash';

    protected $dangerous = true;

    public static function title()
    {
        return __('imageoptimizer::cp.discard-action');
    }

    public function confirmationText()
    {
        return 'imageoptimizer::cp.discard-confirm';
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

            $items->each(fn ($asset) => (new ImageOptimizer)->discardOriginal($asset));

            return;

        }

        $items->each(fn ($asset) => DiscardOriginalJob::dispatch($asset->id()));

        return __('imageoptimizer::cp.discard-queued');

    }

}
