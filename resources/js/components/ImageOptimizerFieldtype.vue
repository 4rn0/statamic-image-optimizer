<template>

    <div v-if="isImage" class="text-sm leading-tight">

        <div v-if="loading" class="flex items-center gap-2 text-gray-600 dark:text-dark-150">
            <ui-icon name="loading" class="size-4" />
            <span>{{ __('imageoptimizer::cp.optimizing') }}...</span>
        </div>

        <div v-else>

            <div v-if="assetValues && assetValues.imageoptimizer" class="space-y-1">
                <div class="text-gray-700 dark:text-dark-150">
                    <span class="text-gray-500 dark:text-dark-200">{{ __('imageoptimizer::cp.original') }}:</span>
                    <span class="font-medium">{{ getBytes(assetValues.imageoptimizer.original_size) }}</span>
                </div>
                <div class="text-gray-700 dark:text-dark-150">
                    <span class="text-gray-500 dark:text-dark-200">{{ __('imageoptimizer::cp.reduced') }}:</span>
                    <span class="font-medium text-green-600 dark:text-green-400">{{ getBytes(savings) }} ({{ percentage }}%)</span>
                </div>
                <ui-button
                    size="sm"
                    class="mt-1.5"
                    @click="doOptimize"
                    :text="__('imageoptimizer::cp.optimize-again')"
                />
            </div>

            <div v-else class="space-y-1">
                <p class="text-gray-600 dark:text-dark-150">{{ __('imageoptimizer::cp.not-optimized') }}</p>
                <ui-button
                    size="sm"
                    @click="doOptimize"
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

    mounted() {
        if (this.publishContainer) {
            this.assetId = this.publishContainer.blueprint.handle + '::' + this.publishContainer.extraValues.path;
            this.assetValues = this.publishContainer.values;
        }
    },

    data() {

        return {

            assetId: null,
            assetValues: null,

            loading: false

        };

    },

    methods: {

        doOptimize() {

            const url = cp_url('utilities/imageoptimizer/' + btoa(this.assetId));

            this.$axios.post(url, {}, this.toEleven).then(response => {

                this.assetValues = response.data.asset.data.values;
                this.loading = false;

            })
            .catch(error => {

				this.loading = false;

            });

            this.loading = true;

        }

    },

    computed: {

        isImage: function() {
            if (!this.publishContainer) return false;

            const mimeType = this.publishContainer.extraValues?.mimeType;
            const extension = this.publishContainer.extraValues?.extension;

            return mimeType && mimeType.startsWith('image/') && extension !== 'svg';
        },

        savings: function() {
            if (!this.assetValues?.imageoptimizer) return 0;
            return this.assetValues.imageoptimizer.original_size - this.assetValues.imageoptimizer.current_size;
        },

        percentage: function() {
            if (!this.assetValues?.imageoptimizer) return 0;
            return ((this.savings / this.assetValues.imageoptimizer.original_size) * 100).toFixed(2);
        }

    }

};

</script>
