<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import type { ContentBlock } from '@/types/blocks';
import type { TiptapContent } from '@/composables';
import { computed, type Component } from 'vue';
import {
    RichTextBlock,
    ImageBlock,
    ImageGalleryBlock,
    HeadingBlock,
    TwoColumnsBlock,
    CalloutBlock,
    DividerBlock,
    CodeBlock,
} from './blocks';
import TiptapRenderer from './TiptapRenderer.vue';

const props = withDefaults(
    defineProps<{
        content: ContentBlock[] | TiptapContent | string | null | undefined;
        scrollable?: boolean;
    }>(),
    {
        scrollable: true,
    },
);

const blockComponents: Record<string, Component> = {
    'rich-text': RichTextBlock,
    image: ImageBlock,
    'image-gallery': ImageGalleryBlock,
    heading: HeadingBlock,
    'two-columns': TwoColumnsBlock,
    callout: CalloutBlock,
    divider: DividerBlock,
    code: CodeBlock,
};

const isBlocksFormat = computed(() => {
    return (
        Array.isArray(props.content) &&
        props.content.length > 0 &&
        typeof props.content[0] === 'object' &&
        'type' in props.content[0] &&
        'data' in props.content[0]
    );
});

const blocks = computed(() => (isBlocksFormat.value ? (props.content as ContentBlock[]) : []));

const hasContent = computed(() => {
    if (isBlocksFormat.value) {
        return blocks.value.length > 0;
    }
    return props.content != null && props.content !== '';
});
</script>

<template>
    <template v-if="isBlocksFormat">
        <ScrollArea v-if="scrollable" class="h-[calc(100vh-150px)] w-full">
            <div v-if="hasContent" class="space-y-6 p-8">
                <component
                    :is="blockComponents[block.type]"
                    v-for="(block, index) in blocks"
                    :key="index"
                    :data="block.data"
                />
            </div>
            <div v-else class="flex h-full items-center justify-center p-12">
                <p class="text-menu/60">No content available</p>
            </div>
        </ScrollArea>
        <template v-else>
            <div v-if="hasContent" class="space-y-6">
                <component
                    :is="blockComponents[block.type]"
                    v-for="(block, index) in blocks"
                    :key="index"
                    :data="block.data"
                />
            </div>
            <div v-else class="flex h-full items-center justify-center p-12">
                <p class="text-menu/60">No content available</p>
            </div>
        </template>
    </template>
    <TiptapRenderer v-else :content="content as TiptapContent | string | null | undefined" />
</template>
