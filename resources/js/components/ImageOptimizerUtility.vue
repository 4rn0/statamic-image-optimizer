<template>

	<div class="image_optimizer-utility flex items-center justify-between">

		<div class="flex items-center text-base text-grey">
			
			<slot></slot>

			<div v-if="statistics.images.length">
			
				{{ translations['imageoptimizer::cp.optimized'] }} {{ statistics.optimized.length }} {{ translations['imageoptimizer::cp.of'] }} {{ statistics.images.length }} {{ translations['imageoptimizer::cp.images'] }}
				<div v-if="filesize">{{ translations['imageoptimizer::cp.reduced'] }} {{ getBytes(filesize) }} ({{ percentage }}%)</div>

			</div>

			<div v-else>
				
				{{ translations['imageoptimizer::cp.empty'] }}

			</div>

		</div>

		<div class="w-1/3 text-right" v-if="optimizing">

			<div class="progress h-3 mb-1 shadow">

				<div class="progress-bar h-full bg-blue transition-width duration-500 ease-in-out" :style="{ width: progress }"></div>

			</div>
			
			<small class="text-s text-grey whitespace-no-wrap">{{ translations['imageoptimizer::cp.optimizing'] }} {{ index + 1 }} {{ translations['imageoptimizer::cp.of'] }} {{ statistics.images.length }} ({{ current }})</small>
		
		</div>

		<div v-if="statistics.images.length && !optimizing">
		
			<button class="btn btn-primary" @click="doOptimizeAll">{{ translations['imageoptimizer::cp.optimize'] }}</button>
			<button class="btn" @click="doOptimizeNew" v-if="statistics.images.length > statistics.optimized.length">{{ translations['imageoptimizer::cp.optimize-new'] }}</button>

		</div>

	</div>

</template>

<script>

import Translations from '../mixins/translations';
import Bytes from '../mixins/bytes';

export default {

	mixins: [Bytes, Fieldtype, Translations],
	props: ['stats'],

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

            let url  = cp_url('utilities/imageoptimizer/' + this.list[this.index] + '?statistics=1');
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