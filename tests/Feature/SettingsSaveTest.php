<?php

namespace Tests\Feature;

use Arnohoogma\StatamicImageOptimizer\Settings;
use Illuminate\Support\Facades\Storage;
use Statamic\Events\AssetUploaded;
use Statamic\Facades\Addon;
use Statamic\Facades\Permission;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Tests\TestCase;

class SettingsSaveTest extends TestCase
{

    private function user(array $permissions)
    {

        Role::make('tester')->permissions(['access cp', ...$permissions])->save();

        return tap(User::make()->email('tester@example.com')->assignRole('tester'))->save();

    }

    private function values(array $overrides = [])
    {

        return array_merge(['assets' => true, 'glide' => true, 'originals' => true, 'log' => false], $overrides);

    }

    public function test_statamic_has_no_separate_settings_screen_for_the_addon()
    {

        $this->assertFalse(Addon::get(Settings::PACKAGE)->hasSettingsBlueprint());

        $this
            ->actingAs(tap(User::make()->email('super@example.com')->makeSuper())->save())
            ->getJson(cp_route('addons.settings.edit', 'statamic-image-optimizer'))
            ->assertNotFound();

    }

    public function test_saving_needs_the_utility_permission()
    {

        $this
            ->actingAs($this->user([]))
            ->patchJson(cp_route('utilities.imageoptimizer.settings'), $this->values(['assets' => false]))
            ->assertForbidden();

        $this->assertTrue(Settings::get('assets'));

    }

    public function test_saved_settings_are_used()
    {

        $this
            ->actingAs($this->user(['access ImageOptimizer utility']))
            ->patchJson(cp_route('utilities.imageoptimizer.settings'), $this->values(['assets' => false, 'log' => true]))
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertFileExists(resource_path('addons/statamic-image-optimizer.yaml'));
        $this->assertFalse(Settings::get('assets'));
        $this->assertTrue(Settings::get('log'));

        // Uploads are no longer optimized
        AssetUploaded::dispatch($this->makeImage(), 'image.png');

        $this->assertSame(1070, Storage::disk('test')->size('image.png'));

    }

    public function test_the_optimizers_permission_is_registered()
    {

        $this->assertTrue(Permission::boot()->all()->map->value()->contains(Settings::PERMISSION));

    }

    public function test_optimizers_and_paths_need_their_own_permission()
    {

        Settings::save(['optimizers' => [['executable' => 'keep', 'arguments' => ':file', 'mimetype' => 'image/png']]]);

        $this
            ->actingAs($this->user(['access ImageOptimizer utility']))
            ->patchJson(cp_route('utilities.imageoptimizer.settings'), $this->values([
                'log' => true,
                'optimizers' => [['executable' => 'evil', 'arguments' => ':file; rm -rf /', 'mimetype' => 'image/png']],
                'paths' => ['/tmp'],
            ]))
            ->assertOk();

        $this->assertTrue(Settings::get('log'));
        $this->assertSame('keep', Settings::get('optimizers')[0]['executable']);
        $this->assertSame(Settings::DEFAULTS['paths'], Settings::get('paths'));

    }

    public function test_optimizers_and_paths_are_saved_with_the_permission()
    {

        $this
            ->actingAs($this->user(['access ImageOptimizer utility', Settings::PERMISSION]))
            ->patchJson(cp_route('utilities.imageoptimizer.settings'), $this->values([
                'optimizers' => [['executable' => 'cjpeg', 'arguments' => '-quality 85 -outfile :temp :file', 'mimetype' => 'image/jpeg']],
                'paths' => ['/opt/tools/bin'],
            ]))
            ->assertOk();

        $this->assertSame([['executable' => 'cjpeg', 'arguments' => '-quality 85 -outfile :temp :file', 'mimetype' => 'image/jpeg']], Settings::get('optimizers'));
        $this->assertSame(['/opt/tools/bin'], Settings::get('paths'));

    }

    public function test_optimizer_rows_are_validated()
    {

        $this
            ->actingAs($this->user(['access ImageOptimizer utility', Settings::PERMISSION]))
            ->patchJson(cp_route('utilities.imageoptimizer.settings'), $this->values([
                'optimizers' => [['executable' => '', 'arguments' => ':file', 'mimetype' => 'image/png']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('optimizers.0.executable');

    }

}
