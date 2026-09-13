<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\Settings;
use Statamic\Facades\Addon;
use Tests\TestCase;

class SettingsTest extends TestCase
{

    public function test_it_falls_back_to_the_defaults()
    {

        $this->resetSettings();

        $this->assertTrue(Settings::get('assets'));
        $this->assertFalse(Settings::get('log'));
        $this->assertSame(Settings::DEFAULTS['optimizers'], Settings::get('optimizers'));
        $this->assertSame(Settings::DEFAULTS['paths'], Settings::get('paths'));
        $this->assertSame(Settings::EDITABLE, array_keys(Settings::DEFAULTS));

    }

    public function test_settings_saved_in_the_control_panel_win()
    {

        Addon::get(Settings::PACKAGE)->settings()->set(['assets' => false, 'log' => true])->save();

        $this->assertFalse(Settings::get('assets'));
        $this->assertTrue(Settings::get('log'));
        $this->assertTrue(Settings::get('glide'));

    }

    public function test_saved_values_keep_the_type_of_the_config_default()
    {

        Addon::get(Settings::PACKAGE)->settings()->set(['assets' => 'false', 'glide' => '1', 'log' => 'true'])->save();

        $this->assertSame(['assets' => false, 'glide' => true, 'originals' => true, 'log' => true], collect(Settings::for())->only(['assets', 'glide', 'originals', 'log'])->all());

    }

    public function test_saved_optimizers_and_paths_win_over_the_defaults()
    {

        Addon::get(Settings::PACKAGE)->settings()->set(['optimizers' => [['executable' => 'x', 'arguments' => ':file', 'mimetype' => 'image/png']], 'paths' => ['/nope']])->save();

        $this->assertSame('x', Settings::get('optimizers')[0]['executable']);
        $this->assertSame(['/nope'], Settings::get('paths'));

    }

    public function test_the_blueprint_offers_the_optimizers_section_on_request_only()
    {

        $this->assertSame(['assets', 'glide', 'originals', 'log'], Settings::blueprint()->fields()->all()->keys()->all());
        $this->assertSame(Settings::EDITABLE, Settings::blueprint(true)->fields()->all()->keys()->all());

    }

    public function test_saving_keeps_the_keys_the_form_did_not_carry()
    {

        Settings::save(['optimizers' => [['executable' => 'x', 'arguments' => ':file', 'mimetype' => 'image/png']]]);
        Settings::save(['log' => true]);

        $this->assertTrue(Settings::get('log'));
        $this->assertSame('x', Settings::get('optimizers')[0]['executable']);

    }

    public function test_the_blueprint_defaults_match_the_defaults()
    {

        // optimizers and paths have no blueprint default: the form shows the effective values instead
        foreach (Settings::blueprint()->fields()->all() as $handle => $field) {

            $this->assertSame(Settings::DEFAULTS[$handle], $field->defaultValue(), $handle);

        }

    }

    public function test_the_executable_column_preloads_the_statuses()
    {

        $field = Settings::blueprint(true)->field('optimizers')->fieldtype()->fields()->get('executable');

        $this->assertSame('image_optimizer_executable', $field->type());
        $this->assertSame('found', $field->fieldtype()->preload()['statuses']['head']['status']);

    }

}
