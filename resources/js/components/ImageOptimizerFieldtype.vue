<template>

    <div v-if="isImage">

        <div v-if="loading">

            <loading-graphic class="mt-1" />

        </div>

        <div v-else>

	    	<div class="help-block" v-if="asset.values.imageoptimizer">

	    		{{ __('imageoptimizer::cp.original') }}: {{ getBytes(asset.values.imageoptimizer.original_size) }}<br>
	    		{{ __('imageoptimizer::cp.reduced') }}: {{ getBytes(savings) }} ({{ percentage }}%)<br>

	    		<a href="#" class="inline-block mt-2 text-red-500" @click.prevent="doOptimize">{{ __('imageoptimizer::cp.optimize-again') }}</a>

	    	</div>

	    	<div class="help-block" v-else>

	    		{{ __('imageoptimizer::cp.not-optimized') }}<br>

	    		<a href="#" class="inline-block mt-2 text-red-500" @click.prevent="doOptimize">{{ __('imageoptimizer::cp.optimize') }}</a>

	    	</div>

	    </div>

    </div>

</template>

<script>

import Bytes from '../mixins/bytes';

export default {

	mixins: [Bytes, Fieldtype],

    created() {

        const portal = this.$stacks.portals[this.$stacks.portals.length - 1];
        this.asset = portal.data.vm.$parent.asset;

    },

    data() {

        return {

            asset: false,
            loading: false

        };

    },

    methods: {

        doOptimize() {

            const url = cp_url('utilities/imageoptimizer/' + btoa(this.asset.id));

            this.$axios.post(url, {}, this.toEleven).then(response => {

                this.asset = response.data.asset.data;
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

            return this.asset && this.asset.isImage && this.asset.extension !== 'svg';

        },

        savings: function() {

            return this.asset.values.imageoptimizer.original_size - this.asset.values.imageoptimizer.current_size;

        },

        percentage: function() {

            return ((this.savings / this.asset.values.imageoptimizer.original_size) * 100).toFixed(2);

        }

    }

};

</script>
