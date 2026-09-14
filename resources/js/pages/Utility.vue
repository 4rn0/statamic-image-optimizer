<template>
    <div>
        <Head :title="__('imageoptimizer::cp.title')" />

        <div class="flex flex-col gap-8">

            <image_optimizer-utility :report="report" :queued="queued"></image_optimizer-utility>

            <image_optimizer-settings :settings="settings" class="imageoptimizer-no-print"></image_optimizer-settings>

            <!-- Editors see the statuses in the settings grid -->
            <ui-panel v-if="!settings.values.optimizers" class="imageoptimizer-no-print">
                <ui-panel-header>
                    <ui-heading>{{ __('imageoptimizer::cp.optimizers') }}</ui-heading>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" v-html="__('imageoptimizer::cp.documentation', { url: docsUrl })"></p>
                </ui-panel-header>
                <ui-card inset>
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
                                                'bg-red-500': optimizer.status === 'missing' || optimizer.status === 'broken',
                                                'bg-amber-500': optimizer.status === 'bundled',
                                                'bg-green-500': optimizer.status === 'found'
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
                </ui-card>
            </ui-panel>

        </div>
    </div>
</template>

<script>
import { Head } from '@statamic/cms/inertia';

export default {
    components: { Head },

    props: {
        report: Object,
        optimizers: Array,
        docsUrl: String,
        settings: Object,
        queued: Boolean,
    },

    methods: {
        getOptimizerTitle(optimizer) {
            return this.__('imageoptimizer::cp.optimizer_' + optimizer.status, { path: optimizer.path });
        }
    }
};
</script>
