<script setup lang="ts">
import type { CalloutBlock } from '@/types/blocks';
import { computed } from 'vue';

const props = defineProps<{
    data: CalloutBlock['data'];
}>();

const config = computed(() => {
    switch (props.data.type) {
        case 'tip':
            return { borderColor: 'border-l-green-500', bgColor: 'bg-green-500/10' };
        case 'warning':
            return { borderColor: 'border-l-amber-500', bgColor: 'bg-amber-500/10' };
        case 'note':
            return { borderColor: 'border-l-purple-500', bgColor: 'bg-purple-500/10' };
        default:
            return { borderColor: 'border-l-cyan-500', bgColor: 'bg-cyan-500/10' };
    }
});
</script>

<template>
    <div
        :class="[
            'border-l-4 px-5 py-4',
            config.borderColor,
            config.bgColor,
        ]"
    >
        <div
            class="prose prose-invert prose-p:text-menu prose-p:leading-relaxed prose-a:text-cyan-400 prose-a:no-underline hover:prose-a:text-cyan-300 prose-strong:text-slate-300 prose-li:text-menu prose-li:leading-relaxed max-w-none prose-p:my-0"
            v-html="data.content"
        />
    </div>
</template>
