<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Facades\AssetContainer;

/**
 * Savings per asset container, computed from the `imageoptimizer` data on every image and kept in
 * the cache. Rebuilt when the utility page opens after images changed, and at the end of every bulk run.
 */
class Report
{

    const KEY = 'imageoptimizer::report';
    const STALE = 'imageoptimizer::report::stale';

    /**
     * The last report, or null when none was built yet (or the cache was cleared)
     *
     * @return array|null
     */
    public static function get()
    {

        return Cache::get(static::KEY);

    }

    /**
     * The report for the utility page: the stored one, rebuilt when images changed since
     *
     * @return array
     */
    public static function current()
    {

        $report = static::get();

        return $report && !Cache::has(static::STALE) ? $report : static::build();

    }

    /**
     * Images changed (optimized, reverted, uploaded, deleted): rebuild before showing the report again
     */
    public static function touch()
    {

        Cache::forever(static::STALE, true);

    }

    /**
     * Walk every image and store the report
     *
     * @return array
     */
    public static function build()
    {

        $containers = AssetContainer::all()->map(fn ($container) => static::container($container))->values();

        $report = [
            'generated_at' => now()->timestamp,
            'containers' => $containers->all(),
            'totals' => [
                'images' => $containers->sum('images'),
                'optimized' => $containers->sum('optimized'),
                'original_size' => $containers->sum('original_size'),
                'current_size' => $containers->sum('current_size'),
                'originals_size' => $containers->sum('originals_size'),
            ],
        ];

        Cache::forever(static::KEY, $report);
        Cache::forget(static::STALE);

        return $report;

    }

    /**
     * One row per image, for the CSV export
     *
     * @return \Generator
     */
    public static function rows()
    {

        foreach (AssetContainer::all() as $container) {

            foreach ($container->assets()->filter->isImage() as $asset) {

                $data = $asset->get('imageoptimizer') ?? [];
                $original = $data['original_size'] ?? null;
                $current = $data['current_size'] ?? null;

                yield [
                    'container' => $container->handle(),
                    'path' => $asset->path(),
                    'original_size' => $original,
                    'current_size' => $current,
                    'saved' => $original !== null ? $original - $current : null,
                    'percent' => $original ? round(($original - $current) / $original * 100, 2) : null,
                    'optimized_at' => isset($data['optimized_at']) ? date('c', $data['optimized_at']) : null,
                    'original_kept' => empty($data['original']) ? 'no' : 'yes',
                ];

            }

        }

    }

    /**
     * @param \Statamic\Contracts\Assets\AssetContainer $container
     * @return array
     */
    private static function container(AssetContainerContract $container)
    {

        $row = [
            'handle' => $container->handle(),
            'title' => $container->title(),
            'images' => 0,
            'optimized' => 0,
            'original_size' => 0,
            'current_size' => 0,
            'originals_size' => 0,
            'last_optimized_at' => null,
        ];

        foreach ($container->assets()->filter->isImage() as $asset) {

            $row['images']++;

            if (!$data = $asset->get('imageoptimizer')) {

                continue;

            }

            $row['optimized']++;
            $row['original_size'] += $data['original_size'] ?? 0;
            $row['current_size'] += $data['current_size'] ?? 0;

            // The stored copy is the original file, so its size is the original size
            if (!empty($data['original'])) {

                $row['originals_size'] += $data['original_size'] ?? 0;

            }

            if (($data['optimized_at'] ?? 0) > $row['last_optimized_at']) {

                $row['last_optimized_at'] = $data['optimized_at'];

            }

        }

        return $row;

    }

}
