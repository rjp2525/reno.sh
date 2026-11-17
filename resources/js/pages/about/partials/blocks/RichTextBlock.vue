<script setup lang="ts">
import { splitCodeBlocks } from '@/lib/codeSegments';
import type { RichTextBlock } from '@/types/blocks';
import { computed } from 'vue';
import CodeBlock from './CodeBlock.vue';

const props = defineProps<{
    data: RichTextBlock['data'];
}>();

// Fenced code (```go) is pulled out and rendered by CodeBlock for syntax highlighting.
const segments = computed(() => splitCodeBlocks(props.data.content));
</script>

<template>
    <div class="space-y-6">
        <template v-for="(segment, i) in segments" :key="i">
            <CodeBlock
                v-if="segment.kind === 'code'"
                :data="{ language: segment.language, code: segment.code }"
            />
            <div
                v-else
                class="blog-prose prose prose-invert prose-headings:text-slate-200 prose-p:text-menu prose-p:my-4 prose-p:leading-relaxed prose-a:text-cyan-400 prose-a:no-underline hover:prose-a:text-cyan-300 prose-strong:text-slate-300 prose-blockquote:border-l-cyan-500 prose-blockquote:text-menu prose-code:text-cyan-400 prose-code:before:content-none prose-code:after:content-none prose-pre:bg-dark-navy/50 prose-li:text-menu prose-li:leading-relaxed max-w-none"
                v-html="segment.html"
            />
        </template>
    </div>
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
