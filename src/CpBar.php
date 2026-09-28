<?php

namespace Arnohoogma\StatamicImageOptimizer;

use Arnohoogma\StatamicImageOptimizer\Jobs\OptimizeAssetJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Query\Builder;
use Statamic\Facades\Asset;
use Statamic\Facades\Collection;
use Statamic\Facades\Glide;
use Statamic\Facades\URL;
use Statamic\Fields\Value;
use Statamic\StaticCaching\Cacher;
use Statamic\Support\Str;
use Statamic\Taxonomies\LocalizedTerm;

class CpBar
{

    public function __invoke($bar, $context)
    {

        $images = collect();
        $page = $context->page;

        if ($page) {
            $this->collect($page->toAugmentedCollection(), $images);
        }

        // An overview gets its images from the template, so look at what the page renders. Only <main>, not the layout.
        if ($images->isEmpty() || $page instanceof LocalizedTerm || ($page && Collection::findByMount($page))) {
            $request = Request::create($context->url);
            $cacher = app(Cacher::class);

            // The page was loaded a moment ago, so the static cache has it. Local certificates (Herd, Valet) don't match the host.
            $html = $cacher->hasCachedPage($request)
                ? $cacher->getCachedPage($request)->content
                : Http::withOptions(['verify' => ! app()->isLocal()])->get($context->url)->body();

            $this->collect(Str::between($html, '<main', '</main>'), $images);
        }

        if ($images->isEmpty()) {
            return;
        }

        $optimized = $images->filter(fn ($asset) => $asset->get('imageoptimizer')['current_size'] ?? null);
        $original = $optimized->sum(fn ($asset) => $asset->get('imageoptimizer')['original_size']);
        $saved = $original - $optimized->sum(fn ($asset) => $asset->get('imageoptimizer')['current_size']);

        $editable = $images->filter(fn ($asset) => $context->user->can('edit', $asset));
        $pending = $editable->diffKeys($optimized);

        $bar->add([
            'id' => 'imageoptimizer',
            'title' => __('imageoptimizer::cp.images'),
            'icon' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><rect x="3" y="4" width="14" height="12" rx="1.5"/><circle cx="7.5" cy="8.5" r="1.5"/><path d="M17 13l-4-4-7 7"/></svg>',
            'can' => 'access ImageOptimizer utility',
            'priority' => 45,
            'meta' => ['dot' => $optimized->count() === $images->count() ? '#22c55e' : '#f59e0b'],
        ]);

        $bar->add([
            'id' => 'imageoptimizer-summary',
            'parent' => 'imageoptimizer',
            'title' => __('imageoptimizer::cp.bar-optimized', ['optimized' => $optimized->count(), 'images' => $images->count()]),
            'meta' => ['text' => true, 'subtitle' => $original ? __('imageoptimizer::cp.bar-saved', [
                'saved' => Str::fileSizeForHumans($saved, 1),
                'percent' => round($saved / $original * 100),
            ]) : null],
        ]);

        // New images first; once there are none, all of them again.
        if ($pending->isNotEmpty()) {
            $bar->add([
                'id' => 'imageoptimizer-optimize',
                'parent' => 'imageoptimizer',
                'title' => trans_choice('imageoptimizer::cp.bar-optimize', $pending->count()),
                'action' => fn () => $this->optimize($pending),
            ]);
        } elseif ($editable->isNotEmpty()) {
            $bar->add([
                'id' => 'imageoptimizer-optimize',
                'parent' => 'imageoptimizer',
                'title' => __('imageoptimizer::cp.optimize-again'),
                'action' => fn () => $this->optimize($editable),
                'confirm' => trans_choice('imageoptimizer::cp.optimize-again-confirm', $editable->count()),
            ]);
        }

    }

    private function collect($value, $images)
    {

        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Multiple assets augment to a query.
        if ($value instanceof Builder) {
            $value = $value->get();
        }

        if ($value instanceof AssetContract) {
            $value->isImage() && $images->put($value->id(), $value);
        } elseif (is_iterable($value)) {
            foreach ($value as $item) {
                $this->collect($item, $images);
            }
        } elseif (is_string($value)) {
            // Markdown and bard augment to HTML, so images in the text are found by their URL.
            preg_match_all('/\s(?:data-)?src(?:set)?=["\']([^"\']+)/i', $value, $matches);

            foreach ($matches[1] as $attribute) {
                foreach (explode(',', $attribute) as $candidate) {
                    $this->collect($this->find(preg_split('/\s+/', trim($candidate))[0]), $images);
                }
            }
        }

    }

    private function find($url)
    {

        $url = URL::removeQueryAndFragment($url);

        // Local containers only match a relative URL, remote ones only an absolute one.
        if ($asset = Asset::findByUrl($url) ?? Asset::findByUrl(URL::makeRelative($url))) {
            return $asset;
        }

        $glide = '/' . trim(URL::makeRelative(Glide::url()), '/') . '/';
        $path = URL::makeRelative($url);

        if (! Str::startsWith($path, $glide)) {
            return null;
        }

        // Through the route: asset/{base64 of container/path}/{filename}. From the cache: containers/{container}/{path}/{hash}/{filename}.
        $segments = explode('/', Str::after($path, $glide));

        return match ($segments[0]) {
            'asset' => Asset::find(Str::replaceFirst('/', '::', Str::fromBase64Url($segments[1] ?? ''))),
            'containers' => Asset::find(($segments[1] ?? '') . '::' . implode('/', array_slice($segments, 2, -2))),
            default => null,
        };

    }

    private function optimize($assets)
    {

        if (config('queue.default') === 'sync') {

            $assets->each(function ($asset) {
                (new ImageOptimizer)->optimizeAsset($asset);
                Glide::clearAsset($asset);
            });

            return ['reload' => true];

        }

        $assets->each(fn ($asset) => OptimizeAssetJob::dispatch($asset->id()));

        return ['message' => __('imageoptimizer::cp.queued')];

    }

}
