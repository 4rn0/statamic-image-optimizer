<template>

    <div class="flex flex-col gap-4">

        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700 dark:text-dark-150">
                <template v-if="statistics.images.length">
                    <span class="font-medium">{{ statistics.optimized.length }}</span> {{ __('imageoptimizer::cp.of') }} <span class="font-medium">{{ statistics.images.length }}</span> {{ __('imageoptimizer::cp.images') }} {{ __('imageoptimizer::cp.optimized') }}
                    <span v-if="filesize" class="text-green-600 dark:text-green-400 font-medium">
                        — {{ __('imageoptimizer::cp.reduced') }} {{ getBytes(filesize) }} ({{ percentage }}%)
                    </span>
                </template>
                <template v-else>
                    <span class="text-gray-500 dark:text-dark-200">{{ __('imageoptimizer::cp.empty') }}</span>
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
            <div class="h-2 bg-gray-200 dark:bg-dark-600 rounded-full overflow-hidden">
                <div
                    class="h-full bg-primary transition-all duration-300 ease-out rounded-full"
                    :style="{ width: progress }"
                ></div>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-dark-200">
                <ui-icon name="loading" class="size-4" />
                <span>{{ __('imageoptimizer::cp.optimizing') }} {{ index + 1 }} {{ __('imageoptimizer::cp.of') }} {{ list.length }}</span>
                <span class="text-gray-400 dark:text-dark-300 truncate">({{ current }})</span>
            </div>
        </div>

    </div>

</template>

<script>

import { useBytes } from '../composables/useBytes.js';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {

    mixins: [Fieldtype],
    props: ['stats'],

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

        };

    },

    methods: {

        doOptimizeNew() {

            this.list = this.statistics.images.filter(item => this.statistics.optimized.indexOf(item) < 0);
            this.doOptimize();

        },

        doOptimizeAll() {

            this.list = this.statistics.images;
            this.doOptimize();

        },

        doOptimize() {

            let url  = cp_url('utilities/imageoptimizer/' + btoa(this.list[this.index]) + '?statistics=1');
                url += (this.index == this.list.length - 1) ? '&clearcache=1' : '';

            this.$axios.post(url, {}, this.toEleven).then(response => {

                if (this.index < this.list.length - 1) {

                    this.$nextTick(this.doOptimize);
                    this.index++;

                }

                else {

                    this.optimizing = false;
                    this.index = 0;

                }

                this.store = response.data.stats;

            })
            .catch(error => {

                this.optimizing = false;
                this.index = 0;

            });

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

            return ((this.filesize / this.statistics.original_size) * 100).toFixed(2);

        },

        progress() {

            return ((this.index / (this.list.length - 1)) * 100) + '%';

        },

        current() {

            return this.list[this.index];

        }

    }

};

</script>
