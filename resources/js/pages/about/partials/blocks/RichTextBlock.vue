<script setup lang="ts">
import type { RichTextBlock } from '@/types/blocks';

defineProps<{
    data: RichTextBlock['data'];
}>();
</script>

<template>
    <div
        class="prose prose-invert prose-headings:text-slate-200 prose-p:text-menu prose-p:my-4 prose-p:leading-relaxed prose-a:text-cyan-400 prose-a:no-underline hover:prose-a:text-cyan-300 prose-strong:text-slate-300 prose-blockquote:border-l-cyan-500 prose-blockquote:text-menu prose-code:text-cyan-400 prose-pre:bg-dark-navy/50 prose-li:text-menu prose-li:my-2 prose-li:leading-relaxed max-w-none"
        v-html="data.content"
    />
</template>
