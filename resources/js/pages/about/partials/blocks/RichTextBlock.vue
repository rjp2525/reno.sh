<script setup lang="ts">
import type { RichTextBlock } from '@/types/blocks';

defineProps<{
    data: RichTextBlock['data'];
}>();
</script>

<template>
    <div
        class="blog-prose prose prose-invert prose-headings:text-slate-200 prose-p:text-menu prose-p:my-4 prose-p:leading-relaxed prose-a:text-cyan-400 prose-a:no-underline hover:prose-a:text-cyan-300 prose-strong:text-slate-300 prose-blockquote:border-l-cyan-500 prose-blockquote:text-menu prose-code:text-cyan-400 prose-pre:bg-dark-navy/50 prose-li:text-menu prose-li:leading-relaxed max-w-none"
        v-html="data.content"
    />
</template>

<style scoped>
.blog-prose :deep(ul),
.blog-prose :deep(ol) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.blog-prose :deep(li) {
    margin-top: 0.125rem;
    margin-bottom: 0.125rem;
}

.blog-prose :deep(li p) {
    margin-top: 0;
    margin-bottom: 0;
}
</style>
