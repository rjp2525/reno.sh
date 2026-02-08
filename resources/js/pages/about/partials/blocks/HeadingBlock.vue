<script setup lang="ts">
import type { HeadingBlock } from '@/types/blocks';

defineProps<{
    data: HeadingBlock['data'];
}>();
</script>

<template>
    <div>
        <component
            :is="data.level"
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
