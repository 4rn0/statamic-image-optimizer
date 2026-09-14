<template>

    <div class="flex items-center gap-2">
        <span class="size-2 shrink-0 rounded-full" :class="dot" :title="title"></span>
        <ui-input
            :model-value="value"
            :read-only="readOnly"
            @update:model-value="update"
        />
    </div>

</template>

<script>

import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {

    mixins: [Fieldtype],

    computed: {

        // A newly typed executable has no status until saved.
        info() {
            return this.meta?.statuses?.[this.value] || { status: 'unknown', path: null };
        },

        dot() {
            return {
                'bg-red-500': this.info.status === 'missing' || this.info.status === 'broken',
                'bg-amber-500': this.info.status === 'bundled',
                'bg-green-500': this.info.status === 'found',
                'bg-gray-300 dark:bg-gray-600': this.info.status === 'unknown',
            };
        },

        title() {
            return this.__('imageoptimizer::cp.optimizer_' + this.info.status, { path: this.info.path });
        }

    }

};

</script>
