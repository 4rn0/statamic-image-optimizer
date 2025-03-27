<template>

    <div v-if="isImage">

        <div v-if="loading">

            <loading-graphic class="mt-1" />

        </div>

        <div v-else>

	    	<div class="help-block" v-if="assetValues.imageoptimizer">

	    		{{ __('imageoptimizer::cp.original') }}: <code>{{ getBytes(assetValues.imageoptimizer.original_size) }}</code><br>
	    		{{ __('imageoptimizer::cp.reduced') }}: <code>{{ getBytes(savings) }}</code> ({{ percentage }}%)<br>

	    		<button type="button" class="btn-primary mt-4" @click.prevent="doOptimize">{{ __('imageoptimizer::cp.optimize-again') }}</button>

	    	</div>

	    	<div class="help-block" v-else>

	    		{{ __('imageoptimizer::cp.not-optimized') }}<br>

	    		<button type="button" class="btn-primary mt-4" @click.prevent="doOptimize">{{ __('imageoptimizer::cp.optimize') }}</button>

	    	</div>

	    </div>

    </div>

</template>

<script>

import Bytes from '../mixins/bytes';

export default {

	mixins: [Bytes, Fieldtype],

    created() {

        this.assetId = this.asset.blueprint.handle + '::' + this.asset.extraValues.path;
        this.assetValues = this.asset.values;

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

        asset: function() {

            return this.$store.state.publish.asset;

        },

        isImage: function() {

            const mimeType = this.asset.extraValues.mimeType;
            const extension = this.asset.extension;

            return this.asset && mimeType.startsWith('image/') && extension !== 'svg';

        },

        savings: function() {

            return this.assetValues.imageoptimizer.original_size - this.assetValues.imageoptimizer.current_size;

        },

        percentage: function() {

            return ((this.savings / this.assetValues.imageoptimizer.original_size) * 100).toFixed(2);

        }

    }

};

</script>
