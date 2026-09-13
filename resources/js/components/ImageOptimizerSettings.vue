<template>

    <ui-panel>
        <ui-panel-header class="flex items-center justify-between gap-4">
            <ui-heading>{{ __('imageoptimizer::cp.settings') }}</ui-heading>
            <ui-button
                size="sm"
                variant="primary"
                :text="__('Save')"
                :disabled="saving"
                @click="save"
            />
        </ui-panel-header>
        <!-- PublishTabs renders the blueprint section as a card of its own -->
        <PublishContainer
            ref="container"
            name="imageoptimizer-settings"
            :blueprint="settings.blueprint"
            :meta="settings.meta"
            :errors="errors"
            v-model="values"
        >
            <PublishTabs />
        </PublishContainer>
    </ui-panel>

</template>

<script>

import { PublishContainer, PublishTabs } from '@statamic/cms/ui';

export default {

    components: { PublishContainer, PublishTabs },

    props: {
        settings: Object,
    },

    data() {

        return {

            values: this.settings.values,
            errors: {},
            saving: false,

        };

    },

    methods: {

        // What Statamic's own settings form does: patch, show errors, mark the container clean
        save() {

            this.saving = true;
            this.errors = {};

            this.$axios.patch(this.settings.submitUrl, this.values).then(() => {

                this.saving = false;
                this.$refs.container?.saved?.();
                Statamic.$toast.success(__('Saved'));

            })
            .catch(error => {

                this.saving = false;

                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors || {};
                }

                Statamic.$toast.error(error.response?.data?.message || __('Something went wrong'));

            });

        }

    }

};

</script>
