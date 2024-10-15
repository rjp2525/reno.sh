<template>
    <component :is="icon" v-bind="props" :class="cn('stroke-2', props.class)" />
</template>

<script lang="ts" setup>
import { cn } from '@/lib/utils';
import type { PropType } from 'vue';
import { computed, defineAsyncComponent } from 'vue';

const props = defineProps({
    name: {
        type: String as PropType<string>,
        required: true,
    },
    class: {
        type: String as PropType<string>,
        required: false,
    },
});

const icon = computed(() =>
    defineAsyncComponent(() => import(`../../svg/icons/${props.name}.svg`)),
);
</script>
