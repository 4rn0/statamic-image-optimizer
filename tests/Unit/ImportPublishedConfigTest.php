<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\Settings;
use Arnohoogma\StatamicImageOptimizer\UpdateScripts\ImportPublishedConfig;
use Illuminate\Console\Command;
use Mockery;
use Statamic\Facades\Addon;
use Tests\TestCase;

class ImportPublishedConfigTest extends TestCase
{

    private function publish(array $config)
    {

        @mkdir(config_path('statamic'), 0755, true);

        file_put_contents(config_path('statamic/imageoptimizer.php'), '<?php return ' . var_export($config, true) . ';');

    }

    private function import()
    {

        (new ImportPublishedConfig(Settings::PACKAGE, Mockery::mock(Command::class)->shouldIgnoreMissing()))->update();

    }

    protected function setUp(): void
    {

        parent::setUp();

        $this->resetSettings();

    }

    protected function tearDown(): void
    {

        @unlink(config_path('statamic/imageoptimizer.php'));

        parent::tearDown();

    }

    public function test_a_published_config_is_imported_once()
    {

        $this->publish([
            'assets' => false,
            'log' => true,
            'optimizers' => [['executable' => 'cjpeg', 'arguments' => '-quality 80 -outfile :temp :file', 'mimetype' => 'image/jpeg']],
            'paths' => ['/srv/bin'],
            'unknown' => 'ignored',
        ]);

        $this->import();

        $this->assertFalse(Settings::get('assets'));
        $this->assertTrue(Settings::get('log'));
        $this->assertSame(['/srv/bin'], Settings::get('paths'));
        $this->assertArrayNotHasKey('unknown', Addon::get(Settings::PACKAGE)->settings()->raw());

        // The published JPEG optimizer stays; the defaults for uncovered mimetypes are appended
        $optimizers = Settings::get('optimizers');

        $this->assertSame('cjpeg', $optimizers[0]['executable']);
        $this->assertSame(['image/jpeg', 'image/gif', 'image/png', 'image/png', 'image/webp'], array_column($optimizers, 'mimetype'));

    }

    public function test_it_does_nothing_without_a_published_config()
    {

        $this->import();

        $this->assertSame([], Addon::get(Settings::PACKAGE)->settings()->raw());

    }

    public function test_it_never_overwrites_saved_settings()
    {

        Settings::save(['assets' => true]);
        $this->publish(['assets' => false]);

        $this->import();

        $this->assertTrue(Settings::get('assets'));

    }

}
