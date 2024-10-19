<script lang="ts" setup>
import { ScrollArea } from '@/components/ui/scroll-area';
import { ref } from 'vue';

interface PageBuilderContent {
    type: string;
    data: {
        content: string;
    };
}

defineProps<{
    content: PageBuilderContent[];
}>();

const delay = ref(0);
</script>

<template>
    <ScrollArea class="h-[calc(100vh-150px)] w-full">
        <div
            class="prose w-full max-w-full px-12 text-sm leading-tight text-menu prose-headings:mt-4 prose-headings:text-slate-300 prose-a:text-slate-400 prose-a:transition-all prose-a:duration-200 prose-a:hover:text-slate-200 prose-blockquote:border-navy prose-blockquote:text-menu"
        >
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <div
                    v-for="section in content"
                    :key="section.data.content"
                    class="relative col-span-1 flex h-full items-center justify-center"
                    :class="`lg:col-span-${section.data.column_span ?? '12'}`"
                >
                    <div
                        v-if="section.type === 'richtext'"
                        v-html="section.data.content"
                    ></div>
                    <div
                        v-else-if="section.type === 'image'"
                        class="flex h-full items-center justify-center"
                    >
                        <img
                            :src="'/storage/' + section.data.content"
                            class="h-96 w-96 rounded border border-navy object-cover"
                        />
                    </div>
                </div>
            </div>
        </div>
    </ScrollArea>
</template>
