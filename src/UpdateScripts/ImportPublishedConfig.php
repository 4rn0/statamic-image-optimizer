<?php

namespace Arnohoogma\StatamicImageOptimizer\UpdateScripts;

use Arnohoogma\StatamicImageOptimizer\Settings;
use Statamic\Facades\Addon;
use Statamic\UpdateScripts\UpdateScript;

/**
 * 1.x read config/statamic/imageoptimizer.php; 2.0 reads the settings saved in the control panel.
 * Carry a published config over once, so a customized site keeps working without manual steps.
 */
class ImportPublishedConfig extends UpdateScript
{

    public function shouldUpdate($newVersion, $oldVersion)
    {

        return $this->isUpdatingTo('2.0.0');

    }

    public function update()
    {

        $path = config_path('statamic/imageoptimizer.php');

        if (!file_exists($path) || Addon::get(Settings::PACKAGE)->settings()->raw()) {

            return;

        }

        $values = collect(require $path)->only(Settings::EDITABLE)->all();

        // cwebp is new since 1.x.
        if (isset($values['optimizers'])) {

            $covered = collect($values['optimizers'])->pluck('mimetype');

            $values['optimizers'] = array_merge(
                array_values($values['optimizers']),
                collect(Settings::DEFAULTS['optimizers'])->reject(fn ($optimizer) => $covered->contains($optimizer['mimetype']))->values()->all()
            );

        }

        Settings::save($values);

        $this->console()->info('ImageOptimizer: imported config/statamic/imageoptimizer.php into resources/addons/statamic-image-optimizer.yaml. The config file is no longer read; you can delete it. Check the Optimizers section on the utility page.');

    }

}
