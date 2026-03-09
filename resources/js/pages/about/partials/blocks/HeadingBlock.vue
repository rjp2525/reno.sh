<script setup lang="ts">
import type { HeadingBlock } from '@/types/blocks';
import { computed } from 'vue';

const props = defineProps<{
    data: HeadingBlock['data'];
}>();

const headingId = computed(() =>
    props.data.title
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-'),
);
</script>

<template>
    <div class="mt-2 -mb-2">
        <component
            :is="data.level"
            :id="headingId"
            class="font-semibold text-slate-200"
            :class="{
                'text-3xl': data.level === 'h2',
                'text-2xl': data.level === 'h3',
                'text-xl': data.level === 'h4',
            }"
        >
            {{ data.title }}
        </component>
        <p v-if="data.subtitle" class="text-menu/50 mt-2 text-base leading-relaxed">
            {{ data.subtitle }}
        </p>
    </div>
</template>
