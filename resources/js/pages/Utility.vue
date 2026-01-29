<template>
    <div>
        <Head :title="__('imageoptimizer::cp.title')" />

        <ui-header :title="__('imageoptimizer::cp.title')" icon="assets"></ui-header>

        <div class="card mb-8">
            <image_optimizer-utility :stats="stats"></image_optimizer-utility>
        </div>

        <div class="mb-8">
            <h2 class="font-semibold text-base mb-1">{{ __('imageoptimizer::cp.configuration') }}</h2>
            <p class="text-sm text-gray-500 dark:text-dark-200 mb-4" v-html="__('imageoptimizer::cp.configuration_path', { path: configPath })"></p>

            <div class="card p-0 overflow-hidden">
                <table class="data-table">
                    <tbody>
                        <tr>
                            <td class="text-left">{{ __('imageoptimizer::cp.asset') }}</td>
                            <td><code class="text-xs">{{ config.assets ? 'true' : 'false' }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-left">{{ __('imageoptimizer::cp.glide') }}</td>
                            <td><code class="text-xs">{{ config.glide ? 'true' : 'false' }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-left">{{ __('imageoptimizer::cp.log') }}</td>
                            <td><code class="text-xs">{{ config.log ? 'true' : 'false' }}</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="font-semibold text-base mb-1">{{ __('imageoptimizer::cp.optimizers') }}</h2>
            <p class="text-sm text-gray-500 dark:text-dark-200 mb-4" v-html="__('imageoptimizer::cp.documentation', { url: 'https://statamic.com/addons/4rn0/imageoptimizer/docs' })"></p>

            <div class="card p-0 overflow-hidden">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="text-left">{{ __('imageoptimizer::cp.executable') }}</th>
                            <th class="text-left">{{ __('imageoptimizer::cp.arguments') }}</th>
                            <th class="text-left">{{ __('imageoptimizer::cp.mimetype') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="optimizer in optimizers" :key="optimizer.executable">
                            <td>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="size-2 rounded-full"
                                        :class="{
                                            'bg-red-500': !optimizer.found,
                                            'bg-amber-500': optimizer.found && optimizer.found.includes('4rn0'),
                                            'bg-green-500': optimizer.found && !optimizer.found.includes('4rn0')
                                        }"
                                        :title="getOptimizerTitle(optimizer)"
                                    ></span>
                                    <code class="text-xs">{{ optimizer.executable }}</code>
                                </div>
                            </td>
                            <td><code class="text-xs text-gray-500">{{ optimizer.arguments }}</code></td>
                            <td><code class="text-xs text-gray-500">{{ optimizer.mimetype }}</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';

export default {
    components: { Head },

    props: {
        stats: Object,
        optimizers: Array,
        configPath: String,
        config: Object,
    },

    methods: {
        getOptimizerTitle(optimizer) {
            if (!optimizer.found) {
                return this.__('imageoptimizer::cp.optimizer_no');
            }
            if (optimizer.found.includes('4rn0')) {
                return this.__('imageoptimizer::cp.optimizer_maybe', { path: optimizer.found });
            }
            return this.__('imageoptimizer::cp.optimizer_yes', { path: optimizer.found });
        }
    }
};
</script>
