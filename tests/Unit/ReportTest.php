<?php

namespace Tests\Unit;

use Arnohoogma\StatamicImageOptimizer\ImageOptimizer;
use Arnohoogma\StatamicImageOptimizer\Report;
use Arnohoogma\StatamicImageOptimizer\Settings;
use Illuminate\Support\Facades\Storage;
use Statamic\Events\AssetUploaded;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

class ReportTest extends TestCase
{

    public function test_there_is_no_report_until_one_is_built()
    {

        $this->assertNull(Report::get());

    }

    public function test_it_sums_per_container_and_in_total()
    {

        Storage::fake('other');
        AssetContainer::make('other')->disk('other')->title('Other')->save();
        Storage::disk('other')->put('c.png', $this->png());
        Storage::disk('other')->put('doc.txt', 'hello');
        AssetContainer::find('other')->makeAsset('doc.txt')->save();

        // optimized with a kept original
        (new ImageOptimizer)->optimizeAsset($this->makeImage('a.png'));

        // optimized before 2.0: statistics without an original
        $this->makeImage('b.png')->set('imageoptimizer', ['original_size' => 500, 'current_size' => 400])->save();

        // never optimized
        $this->makeImage('c.png');
        AssetContainer::find('other')->makeAsset('c.png')->save();

        $report = Report::build();

        $this->assertEqualsWithDelta(time(), $report['generated_at'], 5);

        $test = collect($report['containers'])->firstWhere('handle', 'test');
        $other = collect($report['containers'])->firstWhere('handle', 'other');

        $this->assertSame(3, $test['images']);
        $this->assertSame(2, $test['optimized']);
        $this->assertSame(1570, $test['original_size']);
        $this->assertSame(470, $test['current_size']);
        $this->assertSame(1070, $test['originals_size']);
        $this->assertEqualsWithDelta(time(), $test['last_optimized_at'], 5);

        $this->assertSame('Other', $other['title']);
        $this->assertSame(1, $other['images']);
        $this->assertSame(0, $other['optimized']);
        $this->assertNull($other['last_optimized_at']);

        $this->assertSame([
            'images' => 4,
            'optimized' => 2,
            'original_size' => 1570,
            'current_size' => 470,
            'originals_size' => 1070,
        ], $report['totals']);

        $this->assertSame($report, Report::get());

    }

    public function test_it_lists_one_row_per_image()
    {

        (new ImageOptimizer)->optimizeAsset($this->makeImage('a.png'));
        $this->makeImage('b.png');

        $rows = iterator_to_array(Report::rows(), false);

        $this->assertCount(2, $rows);
        $this->assertSame('a.png', $rows[0]['path']);
        $this->assertSame(1000, $rows[0]['saved']);
        $this->assertSame(93.46, $rows[0]['percent']);
        $this->assertSame('yes', $rows[0]['original_kept']);
        $this->assertNull($rows[1]['original_size']);
        $this->assertSame('no', $rows[1]['original_kept']);

    }

    public function test_changes_to_images_mark_the_report_stale()
    {

        $optimizer = new ImageOptimizer;

        Report::build();
        $this->assertSame(0, Report::current()['totals']['optimized']);

        $optimizer->optimizeAsset($this->makeImage('a.png'));
        $this->assertSame(1, Report::current()['totals']['optimized']);

        $optimizer->revertAsset(Asset::find('test::a.png'));
        $this->assertSame(0, Report::current()['totals']['optimized']);

        Settings::save(['assets' => false]);
        AssetUploaded::dispatch($this->makeImage('b.png'), 'b.png');
        $this->assertSame(2, Report::current()['totals']['images']);

        Asset::find('test::b.png')->delete();
        $this->assertSame(1, Report::current()['totals']['images']);

    }

    public function test_current_builds_when_there_is_no_report_yet()
    {

        $this->makeImage();

        $this->assertNull(Report::get());
        $this->assertSame(1, Report::current()['totals']['images']);
        $this->assertNotNull(Report::get());

    }

}
