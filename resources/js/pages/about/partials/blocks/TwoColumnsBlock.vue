<script setup lang="ts">
import type { TwoColumnsBlock } from '@/types/blocks';
import { computed } from 'vue';

const props = defineProps<{
    data: TwoColumnsBlock['data'];
}>();

const gridCols = computed(() => {
    switch (props.data.ratio) {
        case '33-67':
            return 'md:grid-cols-[1fr_2fr]';
        case '67-33':
            return 'md:grid-cols-[2fr_1fr]';
        default:
            return 'md:grid-cols-2';
    }
});
</script>

<template>
    <div :class="['grid grid-cols-1 gap-8', gridCols]">
        <div
            class="prose prose-invert prose-headings:text-slate-200 prose-p:text-menu prose-p:my-4 prose-p:leading-relaxed prose-a:text-cyan-400 prose-a:no-underline hover:prose-a:text-cyan-300 prose-strong:text-slate-200 prose-li:text-menu prose-li:my-3 prose-li:leading-relaxed max-w-none"
            v-html="data.left"
        />
        <div
            class="prose prose-invert prose-headings:text-slate-200 prose-p:text-menu prose-p:my-4 prose-p:leading-relaxed prose-a:text-cyan-400 prose-a:no-underline hover:prose-a:text-cyan-300 prose-strong:text-slate-200 prose-li:text-menu prose-li:my-3 prose-li:leading-relaxed max-w-none"
            v-html="data.right"
        />
    </div>
</template>
