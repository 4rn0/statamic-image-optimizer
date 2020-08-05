export default {

    computed: {

		translations() {
		
			return this.$store.state.statamic.config.translations;

		}

    }

};