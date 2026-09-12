<template>

    <div class="flex flex-col gap-4">

        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700 dark:text-gray-200">
                <template v-if="statistics.images.length">
                    <span class="font-medium">{{ statistics.optimized.length }}</span> {{ __('imageoptimizer::cp.of') }} <span class="font-medium">{{ statistics.images.length }}</span> {{ __('imageoptimizer::cp.images') }} {{ __('imageoptimizer::cp.optimized') }}
                    <span v-if="filesize" class="text-green-600 dark:text-emerald-300 font-medium">
                        — {{ __('imageoptimizer::cp.reduced') }} {{ getBytes(filesize) }} ({{ percentage }}%)
                    </span>
                </template>
                <template v-else>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('imageoptimizer::cp.empty') }}</span>
                </template>
            </div>

            <div v-if="statistics.images.length && !optimizing" class="flex gap-2">
                <ui-button
                    size="sm"
                    variant="primary"
                    @click="doOptimizeAll"
                    :text="__('imageoptimizer::cp.optimize')"
                />
                <ui-button
                    v-if="statistics.images.length > statistics.optimized.length"
                    size="sm"
                    @click="doOptimizeNew"
                    :text="__('imageoptimizer::cp.optimize-new')"
                />
            </div>
        </div>

        <div v-if="optimizing" class="space-y-2">
            <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div
                    class="h-full bg-blue-500 transition-all duration-300 ease-out rounded-full"
                    :style="{ width: progress }"
                ></div>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <ui-icon name="loading" class="size-4" />
                <span>{{ __('imageoptimizer::cp.optimizing') }} {{ Math.min(index + 1, list.length) }} {{ __('imageoptimizer::cp.of') }} {{ list.length }}</span>
                <span v-if="current" class="text-gray-400 dark:text-gray-400 truncate">({{ current }})</span>
            </div>
        </div>

    </div>

</template>

<script>

import { useBytes } from '../composables/useBytes.js';

export default {

    props: ['stats', 'queued'],

    setup() {
        const { getBytes } = useBytes();
        return { getBytes };
    },

    data() {

        return {

            optimizing: false,
            store: false,
            list: [],
            index: 0,
            run: null,

        };

    },

    methods: {

        doOptimizeNew() {

            if (this.queued) return this.doRun('new');

            this.list = this.statistics.images.filter(item => this.statistics.optimized.indexOf(item) < 0);
            this.doOptimize();

        },

        doOptimizeAll() {

            if (this.queued) return this.doRun('all');

            this.list = this.statistics.images;
            this.doOptimize();

        },

        // With a queue: one request starts the run, then poll its progress
        doRun(only) {

            this.optimizing = true;

            this.$axios.post(cp_url('utilities/imageoptimizer/run'), { only }).then(response => {

                this.run = response.data;
                this.list = new Array(response.data.total);
                this.index = 0;
                this.poll();

            })
            .catch(error => this.fail(error));

        },

        poll() {

            this.$axios.get(cp_url('utilities/imageoptimizer/run/' + this.run.run)).then(response => {

                this.index = response.data.done;

                if (response.data.done < response.data.total) {

                    setTimeout(this.poll, 1500);

                }

                else {

                    this.optimizing = false;
                    this.index = 0;
                    this.store = response.data.stats;

                }

            })
            .catch(error => this.fail(error));

        },

        fail(error) {

            this.optimizing = false;
            this.index = 0;
            Statamic.$toast.error(error.response?.data?.message || __('imageoptimizer::cp.error'));

        },

        // Without a queue: optimize one image per request
        doOptimize() {

            const last = this.index == this.list.length - 1;
            const url = cp_url('utilities/imageoptimizer/' + btoa(this.list[this.index]) + (last ? '?statistics=1&clearcache=1' : ''));

            this.$axios.post(url).then(response => {

                if (!last) {

                    this.$nextTick(this.doOptimize);
                    this.index++;

                }

                else {

                    this.optimizing = false;
                    this.index = 0;
                    this.store = response.data.stats;

                }

            })
            .catch(error => this.fail(error));

            this.optimizing = true;

        }

    },

    computed: {

        statistics() {

            return this.store ? this.store : this.stats;

        },

        filesize() {

            return this.statistics.original_size - this.statistics.current_size;

        },

        percentage() {

            if (!this.statistics.original_size) return 0;
            return ((this.filesize / this.statistics.original_size) * 100).toFixed(2);

        },

        progress() {

            // the item in progress counts, so the bar reaches 100% while the last one runs
            return ((Math.min(this.index + 1, this.list.length) / this.list.length) * 100) + '%';

        },

        current() {

            return this.list[this.index];

        }

    }

};

</script>
