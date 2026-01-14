<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import { useTiptapRenderer, type TiptapContent } from '@/composables';

const props = defineProps<{
    content: TiptapContent | string | null | undefined;
}>();

const { renderedHtml, hasContent } = useTiptapRenderer(() => props.content);
</script>

<template>
    <ScrollArea class="h-[calc(100vh-150px)] w-full">
        <div
            v-if="hasContent"
            class="prose prose-invert prose-headings:text-slate-200 prose-p:text-menu prose-a:text-cyan-400 prose-a:no-underline hover:prose-a:text-cyan-300 prose-strong:text-slate-300 prose-blockquote:border-l-cyan-500 prose-blockquote:text-menu prose-code:text-cyan-400 prose-pre:bg-dark-navy/50 prose-li:text-menu max-w-none p-8"
            v-html="renderedHtml"
        />
        <div v-else class="flex h-full items-center justify-center p-12">
            <p class="text-menu/60">No content available</p>
        </div>
    </ScrollArea>
</template>
