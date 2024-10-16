<script lang="ts" setup>
import { ScrollArea } from '@/components/ui/scroll-area';

interface PageBuilderContent {
    type: string;
    data: {
        content: string;
    };
}

defineProps<{
    content: PageBuilderContent[];
}>();
</script>

<template>
    <ScrollArea class="h-[calc(100vh-150px)] w-full">
        <div
            class="prose w-full max-w-full px-12 text-sm leading-tight text-menu prose-headings:text-slate-300 prose-a:text-slate-400 prose-a:transition-all prose-a:duration-200 prose-a:hover:text-slate-200 prose-blockquote:border-navy prose-blockquote:text-menu"
        >
            <div v-for="section in content" :key="section.data.content">
                <div
                    v-if="section.type === 'richtext'"
                    v-html="section.data.content"
                ></div>
            </div>
        </div>
    </ScrollArea>
</template>
