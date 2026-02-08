<script setup lang="ts">
import type { DividerBlock } from '@/types/blocks';

defineProps<{
    data: DividerBlock['data'];
}>();
</script>

<template>
    <div class="my-2">
        <div
            v-if="data.style === 'gradient'"
            class="h-px bg-gradient-to-r from-transparent via-slate-500/60 to-transparent"
        />
        <hr
            v-else
            :class="[
                'border-slate-700',
                {
                    'border-solid': data.style === 'solid',
                    'border-dashed': data.style === 'dashed',
                    'border-dotted': data.style === 'dotted',
                },
            ]"
        />
    </div>
</template>
