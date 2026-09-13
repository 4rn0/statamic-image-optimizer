<?php

namespace Arnohoogma\StatamicImageOptimizer\Listeners;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Report;
use Statamic\Events\AssetDeleted;

class DeleteOriginal
{

    public function handle(AssetDeleted $event)
    {

        (new ImageOptimizer)->deleteOriginal($event->asset);

        if ($event->asset->isImage()) {

            Report::touch();

        }

    }

}
