<template>

    <div class="text-sm leading-tight">

        <div v-if="busy" class="flex items-center gap-2 text-gray-600 dark:text-gray-200">
            <ui-icon name="loading" class="size-4" />
            <span>{{ __('imageoptimizer::cp.' + busy) }}...</span>
        </div>

        <div v-else>

            <div v-if="data" class="space-y-1">
                <div class="text-gray-700 dark:text-gray-200">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('imageoptimizer::cp.original') }}:</span>
                    <span class="font-medium">{{ getBytes(data.original_size) }}</span>
                </div>
                <div class="text-gray-700 dark:text-gray-200">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('imageoptimizer::cp.reduced') }}:</span>
                    <span class="font-medium text-green-600 dark:text-emerald-300">{{ getBytes(savings) }} ({{ percentage }}%)</span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ data.original ? __('imageoptimizer::cp.original-kept') : __('imageoptimizer::cp.original-none') }}
                </p>
                <div class="flex gap-2 mt-2">
                    <ui-button
                        size="sm"
                        @click="optimize"
                        :text="__('imageoptimizer::cp.optimize-again')"
                    />
                    <ui-button
                        v-if="data.original"
                        size="sm"
                        @click="revert"
                        :text="__('imageoptimizer::cp.revert')"
                    />
                </div>
            </div>

            <div v-else class="space-y-1">
                <p class="text-gray-600 dark:text-gray-200">{{ __('imageoptimizer::cp.not-optimized') }}</p>
                <ui-button
                    size="sm"
                    @click="optimize"
                    :text="__('imageoptimizer::cp.optimize')"
                />
            </div>

        </div>

    </div>

</template>

<script>

import { useBytes } from '../composables/useBytes.js';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {

    mixins: [Fieldtype],

    setup() {
        const { getBytes } = useBytes();
        return { getBytes };
    },

    data() {

        return {

            // The field is computed: the editor never saves it, so this local copy is the truth
            data: this.value || null,

            busy: null

        };

    },

    methods: {

        optimize() {

            this.request('optimizing', cp_url('utilities/imageoptimizer/' + btoa(this.config.asset) + '?clearcache=1'));

        },

        revert() {

            this.request('reverting', cp_url('utilities/imageoptimizer/' + btoa(this.config.asset) + '/revert'), response => {

                if (!response.data.reverted) {
                    Statamic.$toast.error(__('imageoptimizer::cp.revert-missing'));
                }

            });

        },

        request(busy, url, then) {

            this.busy = busy;

            this.$axios.post(url).then(response => {

                this.data = response.data.asset.data.values.imageoptimizer || null;
                this.busy = null;

                if (then) then(response);

            })
            .catch(error => {

                this.busy = null;
                Statamic.$toast.error(error.response?.data?.message || __('imageoptimizer::cp.error'));

            });

        }

    },

    computed: {

        savings: function() {
            if (!this.data) return 0;
            return this.data.original_size - this.data.current_size;
        },

        percentage: function() {
            const original = this.data?.original_size;
            if (!original) return 0;
            return ((this.savings / original) * 100).toFixed(2);
        }

    }

};

</script>
