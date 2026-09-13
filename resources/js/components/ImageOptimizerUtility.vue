<template>

    <div class="flex flex-col gap-8">

    <!-- Header and loader share one flex child, so the page gap does not open up between them -->
    <div>
    <ui-header :title="__('imageoptimizer::cp.title')" icon="assets">
        <div v-if="!busy" class="flex gap-2 imageoptimizer-no-print">
            <ui-button
                variant="primary"
                @click="optimizeAll"
                :text="__('imageoptimizer::cp.optimize')"
            />
            <ui-button
                v-if="figures.totals.images > figures.totals.optimized"
                @click="optimizeNew"
                :text="__('imageoptimizer::cp.optimize-new')"
            />
        </div>
    </ui-header>

    <div v-if="busy" class="-mt-4 space-y-2">
        <div v-if="total" class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
            <div
                class="h-full bg-blue-500 transition-all duration-300 ease-out rounded-full"
                :style="{ width: progress }"
            ></div>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <ui-icon name="loading" class="size-4" />
            <span v-if="total">{{ __('imageoptimizer::cp.progress', { current: Math.min(index + 1, total), total }) }}</span>
            <span v-else>{{ __('imageoptimizer::cp.optimizing') }}...</span>
        </div>
    </div>
    </div>

    <ui-panel>
        <ui-panel-header class="flex items-center justify-between gap-4">
            <ui-heading>{{ __('imageoptimizer::cp.report') }}</ui-heading>
            <ui-button
                v-if="!busy"
                size="sm"
                class="imageoptimizer-no-print"
                @click="exportCsv"
                :text="__('imageoptimizer::cp.export')"
            />
        </ui-panel-header>
        <ui-card class="flex flex-col gap-4">

            <div class="text-sm text-gray-700 dark:text-gray-200">
                <div v-html="__('imageoptimizer::cp.summary', { optimized: figures.totals.optimized, images: figures.totals.images, saved: getBytes(saved), percent })"></div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('imageoptimizer::cp.as-of', { time: generated }) }}</div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th v-if="multiple" class="text-left">{{ __('imageoptimizer::cp.container') }}</th>
                            <th>{{ __('imageoptimizer::cp.images') }}</th>
                            <th>{{ __('imageoptimizer::cp.optimized') }}</th>
                            <th>{{ __('imageoptimizer::cp.original-size') }}</th>
                            <th>{{ __('imageoptimizer::cp.current-size') }}</th>
                            <th>{{ __('imageoptimizer::cp.saved') }}</th>
                            <th>{{ __('imageoptimizer::cp.originals-size') }}</th>
                            <th class="text-left">{{ __('imageoptimizer::cp.last-optimized') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="container in figures.containers" :key="container.handle">
                            <td v-if="multiple">{{ container.title }}</td>
                            <td>{{ container.images }}</td>
                            <td>{{ container.optimized }}</td>
                            <td>{{ getBytes(container.original_size) }}</td>
                            <td>{{ getBytes(container.current_size) }}</td>
                            <td class="text-green-600 dark:text-emerald-300">{{ getBytes(container.original_size - container.current_size) }} ({{ percentOf(container) }}%)</td>
                            <td>{{ getBytes(container.originals_size) }}</td>
                            <td>{{ container.last_optimized_at ? date(container.last_optimized_at) : '' }}</td>
                        </tr>
                        <tr v-if="multiple" class="font-medium">
                            <td>{{ __('imageoptimizer::cp.total') }}</td>
                            <td>{{ figures.totals.images }}</td>
                            <td>{{ figures.totals.optimized }}</td>
                            <td>{{ getBytes(figures.totals.original_size) }}</td>
                            <td>{{ getBytes(figures.totals.current_size) }}</td>
                            <td class="text-green-600 dark:text-emerald-300">{{ getBytes(saved) }} ({{ percent }}%)</td>
                            <td>{{ getBytes(figures.totals.originals_size) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </ui-card>
    </ui-panel>

    </div>

</template>

<script>

import { useBytes } from '../composables/useBytes.js';

export default {

    props: {
        report: Object,
        queued: Boolean,
    },

    setup() {
        const { getBytes } = useBytes();
        return { getBytes };
    },

    data() {

        return {

            // 'optimizing' while a run is going
            busy: null,

            // the report as rebuilt by the server after a run or a refresh; the prop is the one from page load
            store: null,

            list: [],
            index: 0,
            total: 0,
            run: null,
            timer: null,

        };

    },

    beforeUnmount() {

        clearTimeout(this.timer);

    },

    methods: {

        optimizeAll() {

            this.queued ? this.startRun('all') : this.startLoop('all');

        },

        optimizeNew() {

            this.queued ? this.startRun('new') : this.startLoop('new');

        },

        exportCsv() {

            window.location = cp_url('utilities/imageoptimizer/report.csv');

        },

        // With a queue: one request starts the run, then poll its progress
        startRun(only) {

            this.busy = 'optimizing';
            this.total = 0;

            this.$axios.post(cp_url('utilities/imageoptimizer/run'), { only }).then(response => {

                this.run = response.data.run;
                this.total = response.data.total;
                this.index = 0;

                this.total ? this.poll() : this.finish(this.figures);

            })
            .catch(error => this.fail(error));

        },

        poll() {

            this.$axios.get(cp_url('utilities/imageoptimizer/run/' + this.run)).then(response => {

                this.index = response.data.done;

                if (response.data.done < response.data.total) {

                    this.timer = setTimeout(this.poll, 1500);

                }

                else {

                    this.finish(response.data.report);

                }

            })
            .catch(error => this.fail(error));

        },

        // Without a queue: fetch the list, then optimize one image per request
        startLoop(only) {

            this.busy = 'optimizing';
            this.total = 0;

            this.$axios.get(cp_url('utilities/imageoptimizer/images?only=' + only)).then(response => {

                this.list = response.data.images;
                this.total = this.list.length;
                this.index = 0;

                this.total ? this.next() : this.finish(this.figures);

            })
            .catch(error => this.fail(error));

        },

        next() {

            const last = this.index === this.list.length - 1;
            const url = cp_url('utilities/imageoptimizer/' + btoa(this.list[this.index]) + '?clearcache=1' + (last ? '&report=1' : ''));

            this.$axios.post(url).then(response => {

                if (last) {

                    this.finish(response.data.report);

                }

                else {

                    this.index++;
                    this.next();

                }

            })
            .catch(error => this.fail(error));

        },

        finish(report) {

            this.store = report;
            this.busy = null;
            this.total = 0;
            this.index = 0;

        },

        fail(error) {

            clearTimeout(this.timer);
            this.busy = null;
            this.total = 0;
            this.index = 0;
            Statamic.$toast.error(error.response?.data?.message || __('imageoptimizer::cp.error'));

        },

        percentOf(row) {

            if (!row.original_size) return 0;
            return (((row.original_size - row.current_size) / row.original_size) * 100).toFixed(2);

        },

        date(timestamp) {

            return new Date(timestamp * 1000).toLocaleDateString();

        }

    },

    computed: {

        figures() {

            return this.store || this.report;

        },

        // One container: no container column, no totals row
        multiple() {

            return this.figures.containers.length > 1;

        },

        saved() {

            return this.figures.totals.original_size - this.figures.totals.current_size;

        },

        percent() {

            return this.percentOf(this.figures.totals);

        },

        generated() {

            return new Date(this.figures.generated_at * 1000).toLocaleString();

        },

        progress() {

            // the item in progress counts, so the bar reaches 100% while the last one runs
            return ((Math.min(this.index + 1, this.total) / this.total) * 100) + '%';

        }

    }

};

</script>
