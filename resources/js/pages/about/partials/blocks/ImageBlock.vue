<script setup lang="ts">
import ImageViewer from '@/components/ImageViewer.vue';
import { imageUrl } from '@/lib/utils';
import type { ImageBlock } from '@/types/blocks';
import { Expand } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    data: ImageBlock['data'];
}>();

const src = computed(() => imageUrl(props.data.image) ?? '');
const alt = computed(() => props.data.alt || props.data.caption || '');
const viewerIndex = ref<number | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
</script>

<template>
    <figure
        :class="[
            'my-2',
            {
                'text-left': data.alignment === 'left',
                'text-center': data.alignment === 'center',
                'text-right': data.alignment === 'right',
                'w-full': data.alignment === 'full',
            },
        ]"
    >
        <button
            ref="trigger"
            type="button"
            class="zoomable group relative cursor-zoom-in overflow-hidden rounded-lg align-top"
            :class="data.alignment === 'full' ? 'block w-full' : 'inline-block max-w-md'"
            :aria-label="alt ? `View larger: ${alt}` : 'View larger image'"
            @click="viewerIndex = 0"
        >
            <img
                :src="src"
                :alt="alt"
                loading="lazy"
                class="block w-full rounded-lg transition duration-300 group-hover:scale-[1.015] group-hover:brightness-110"
            />
            <span
                class="pointer-events-none absolute top-2 right-2 flex items-center gap-1 rounded border border-[#1E2D3D] bg-[#011221]/85 px-1.5 py-0.5 font-mono text-[10px] text-[#43D9AD] opacity-0 backdrop-blur-sm transition group-hover:opacity-100 group-focus-visible:opacity-100"
                aria-hidden="true"
            >
                <Expand class="h-3 w-3" /> view
            </span>
        </button>
        <figcaption
            v-if="data.caption"
            class="text-menu/60 mt-2 text-sm"
        >
            {{ data.caption }}
        </figcaption>

        <ImageViewer
            v-model:index="viewerIndex"
            :images="[{ src, alt: data.alt, caption: data.caption }]"
            :origin="trigger"
            @close="viewerIndex = null"
        />
    </figure>
</template>

<style scoped>
.zoomable:focus-visible {
    outline: 1px solid #43d9ad;
    outline-offset: 3px;
}
</style>
