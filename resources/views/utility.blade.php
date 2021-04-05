@extends('statamic::layout')
@inject('str', 'Statamic\Support\Str')
@section('title', __('imageoptimizer::cp.title'))

@section('content')

<header class="mb-3">

    @include('statamic::partials.breadcrumb', ['url' => cp_route('utilities.index'), 'title' => __('Utilities')])
    <h1>{{ __('imageoptimizer::cp.title') }}</h1>

</header>

<image_optimizer-utility :stats='@json($stats)'>

	<div class="h-12 w-12 mr-2">
		@cp_svg('assets')
	</div>

</image_optimizer-utility>

<h2 class="mt-5 mb-1 font-bold text-lg">{{ __('imageoptimizer::cp.configuration') }}</h2>
<p class="text-sm text-grey mb-2">{!! __('imageoptimizer::cp.configuration_path', ['path' => config_path('statamic/imageoptimizer.php')]) !!}</p>

<div class="card p-0">

    <table class="data-table">

        <tr>
            <th class="pl-2 py-1 w-1/4">{{ __('imageoptimizer::cp.asset') }}</th>
            <td><code class="text-grey">{{ config('statamic.imageoptimizer.assets') ? 'true' : 'false' }}</code></td>
        </tr>

        <tr>
            <th class="pl-2 py-1 w-1/4">{{ __('imageoptimizer::cp.glide') }}</th>
            <td><code class="text-grey">{{ config('statamic.imageoptimizer.glide') ? 'true' : 'false' }}</code></td>
        </tr>

        <tr>
            <th class="pl-2 py-1 w-1/4">{{ __('imageoptimizer::cp.log') }}</th>
            <td><code class="text-grey">{{ config('statamic.imageoptimizer.log') ? 'true' : 'false' }}</code></td>
        </tr>

    </table>

</div>

<h3 class="mt-5 mb-1 font-bold text-lg">{{ __('imageoptimizer::cp.optimizers') }}</h3>
<p class="text-sm text-grey mb-2">{!! __('imageoptimizer::cp.documentation', ['url' => 'https://statamic.com/addons/4rn0/imageoptimizer-v3/docs']) !!}</p>

<div class="card p-0">

	<table class="data-table">

		<thead>

			<tr>

				<th>{{ __('imageoptimizer::cp.executable') }}</th>
				<th>{{ __('imageoptimizer::cp.arguments') }}</th>
				<th>{{ __('imageoptimizer::cp.mimetype') }}</th>

			</tr>

		</thead>

		<tbody>

			@foreach ($optimizers as $optimizer)

			<tr>

				<td>

					@if (!$optimizer['found'])
					<span class="little-dot bg-red mr-1"></span>
					<code class="text-grey" title="{!! __('imageoptimizer::cp.optimizer_no', ['path' => $optimizer['found']]) !!}">{{ $optimizer['executable'] }}</code>
					@elseif ($str->contains($optimizer['found'], '4rn0'))
					<span class="little-dot bg-orange mr-1"></span>
					<code class="text-grey" title="{!! __('imageoptimizer::cp.optimizer_maybe', ['path' => $optimizer['found']]) !!}">{{ $optimizer['executable'] }}</code>
					@else
					<span class="little-dot bg-green mr-1"></span>
					<code class="text-grey" title="{!! __('imageoptimizer::cp.optimizer_yes', ['path' => $optimizer['found']]) !!}">{{ $optimizer['executable'] }}</code>
					@endif

				</td>

				<td><code class="text-grey">{{ $optimizer['arguments'] }}</code></td>
				<td><code class="text-grey">{{ $optimizer['mimetype'] }}</code></td>

			</tr>

			@endforeach

		</tbody>

	</table>

</div>

@endsection
