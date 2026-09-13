<?php

namespace Arnohoogma\StatamicImageOptimizer\Fieldtypes;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Statamic\Fields\Fieldtype;

/**
 * The executable column of the optimizers grid: a text input with the binary's status next to it
 */
class ImageOptimizerExecutableFieldtype extends Fieldtype
{

    protected $selectable = false;

    /**
     * The status of every configured executable, so the grid can show a dot per row without a request
     */
    public function preload()
    {

        $optimizer = new ImageOptimizer();

        return [
            'statuses' => collect(Settings::get('optimizers'))
                ->pluck('executable')
                ->unique()
                ->mapWithKeys(fn ($executable) => [$executable => $optimizer->status($executable)])
                ->all(),
        ];

    }

}
