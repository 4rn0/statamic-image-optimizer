<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Statamic\Contracts\Assets\AssetContainer;
use Statamic\Facades\Addon;
use Statamic\Facades\Blueprint;
use Statamic\Facades\YAML;

class Settings
{

    const PACKAGE = '4rn0/statamic-image-optimizer';

    /**
     * Used until a setting is saved in the control panel. `:file` is the image, `:temp` an output
     * file for tools that cannot write in place. jpegoptim's -m and cwebp's -q are the quality.
     */
    const DEFAULTS = [
        'assets' => true,
        'glide' => true,
        'originals' => true,
        'log' => false,
        'optimizers' => [
            ['executable' => 'jpegoptim', 'arguments' => '--all-progressive -m85 :file', 'mimetype' => 'image/jpeg'],
            ['executable' => 'gifsicle', 'arguments' => '-b -O3 :file', 'mimetype' => 'image/gif'],
            ['executable' => 'pngquant', 'arguments' => '--force --output=:file :file', 'mimetype' => 'image/png'],
            ['executable' => 'optipng', 'arguments' => '-i0 -o2 :file', 'mimetype' => 'image/png'],
            ['executable' => 'cwebp', 'arguments' => '-quiet -m 6 -q 85 -metadata icc :file -o :temp', 'mimetype' => 'image/webp'],
        ],
        'paths' => [
            '/opt/homebrew/bin',
            '/opt/homebrew/sbin',
            '/usr/local/bin',
            '/usr/local/sbin',
            '/usr/bin',
            '/usr/sbin',
            '/bin',
            '/sbin',
        ],
    ];

    /**
     * Settings that can be changed in the control panel
     */
    const EDITABLE = ['assets', 'glide', 'originals', 'log', 'optimizers', 'paths'];

    /**
     * Optimizers and paths are shell commands and search paths: editing them is shell access to
     * the server, so it takes this permission on top of the utility permission. Super users have it.
     */
    const PERMISSION = 'edit ImageOptimizer optimizers';

    /**
     * All settings for one asset container: what was saved in the control panel over the defaults.
     * The container is not used yet; per-container settings plug in here.
     *
     * @param \Statamic\Contracts\Assets\AssetContainer|null $container
     * @return array
     */
    public static function for(?AssetContainer $container = null)
    {

        $settings = static::DEFAULTS;
        $saved = static::saved();

        foreach (static::EDITABLE as $key) {

            if (array_key_exists($key, $saved)) {

                $settings[$key] = static::cast($saved[$key], $settings[$key]);

            }

        }

        return $settings;

    }

    /**
     * One setting
     *
     * @param string $key
     * @param \Statamic\Contracts\Assets\AssetContainer|null $container
     * @return mixed
     */
    public static function get($key, ?AssetContainer $container = null)
    {

        return static::for($container)[$key] ?? null;

    }

    /**
     * The form, rendered on the utility page. Deliberately not registered as Statamic's addon
     * settings blueprint: that would add a second settings screen under Tools > Addons.
     *
     * @param bool $withOptimizers include the optimizers section
     * @return \Statamic\Fields\Blueprint
     */
    public static function blueprint($withOptimizers = false)
    {

        $contents = YAML::file(__DIR__ . '/../resources/settings.yaml')->parse();

        if (!$withOptimizers) {

            array_pop($contents['tabs']['main']['sections']);

        }

        return Blueprint::make('imageoptimizer')->setContents($contents);

    }

    /**
     * Save values from the form, keeping the keys the form did not carry (the optimizers section
     * of a user without the permission)
     *
     * @param array $values
     */
    public static function save(array $values)
    {

        $settings = Addon::get(static::PACKAGE)->settings();

        $settings->set(array_merge($settings->raw(), $values))->save();

    }

    /**
     * Settings saved in the control panel (resources/addons/statamic-image-optimizer.yaml)
     *
     * @return array
     */
    private static function saved()
    {

        return Addon::get(static::PACKAGE)?->settings()->raw() ?? [];

    }

    /**
     * Keep the type of the default; the YAML may hold "1", "true" or "85"
     *
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    private static function cast($value, $default)
    {

        return match (true) {
            is_bool($default) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            is_int($default) => (int) $value,
            is_array($default) => array_values((array) $value),
            default => $value,
        };

    }

}
