<script setup lang="ts">
import ImageViewer from '@/components/ImageViewer.vue';
import { imageUrl } from '@/lib/utils';
import type { ImageGalleryBlock } from '@/types/blocks';
import { Expand } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    data: ImageGalleryBlock['data'];
}>();

const images = computed(() =>
    props.data.images.map((item) => ({ src: imageUrl(item.image) ?? '', alt: item.alt, caption: item.caption })),
);
const viewerIndex = ref<number | null>(null);
const triggers = ref<HTMLButtonElement[]>([]);
// Return focus/animate from the thumbnail of whichever image is showing.
const origin = computed(() => (viewerIndex.value !== null ? triggers.value[viewerIndex.value] : null));
</script>

<template>
    <div
        class="my-2 grid gap-4"
        :style="{ gridTemplateColumns: `repeat(${data.columns}, minmax(0, 1fr))` }"
    >
        <figure
            v-for="(item, index) in images"
            :key="index"
        >
            <button
                ref="triggers"
                type="button"
                class="zoomable group relative block w-full cursor-zoom-in overflow-hidden rounded-lg"
                :aria-label="item.alt || item.caption ? `View larger: ${item.alt || item.caption}` : `View image ${index + 1} of ${images.length}`"
                @click="viewerIndex = index"
            >
                <img
                    :src="item.src"
                    :alt="item.alt || item.caption || ''"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                />
                <span
                    class="pointer-events-none absolute top-2 right-2 flex items-center gap-1 rounded border border-[#1E2D3D] bg-[#011221]/85 px-1.5 py-0.5 font-mono text-[10px] text-[#43D9AD] opacity-0 backdrop-blur-sm transition group-hover:opacity-100 group-focus-visible:opacity-100"
                    aria-hidden="true"
                >
                    <Expand class="h-3 w-3" /> {{ index + 1 }}/{{ images.length }}
                </span>
            </button>
            <figcaption
                v-if="item.caption"
                class="text-menu/60 mt-1 text-sm"
            >
                {{ item.caption }}
            </figcaption>
        </figure>

        <ImageViewer
            v-model:index="viewerIndex"
            :images="images"
            :origin="origin"
            @close="viewerIndex = null"
        />
    </div>
</template>

<style scoped>
.zoomable:focus-visible {
    outline: 1px solid #43d9ad;
    outline-offset: 3px;
}
</style>
