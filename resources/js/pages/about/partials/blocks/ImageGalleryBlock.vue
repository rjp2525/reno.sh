<script setup lang="ts">
import type { ImageGalleryBlock } from '@/types/blocks';

defineProps<{
    data: ImageGalleryBlock['data'];
}>();
</script>

<template>
    <div
        class="my-2 grid gap-4"
        :style="{ gridTemplateColumns: `repeat(${data.columns}, minmax(0, 1fr))` }"
    >
        <figure
            v-for="(item, index) in data.images"
            :key="index"
            class="group overflow-hidden rounded-lg"
        >
            <img
                :src="`/img/${item.image}`"
                :alt="item.caption ?? ''"
                loading="lazy"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
            <figcaption
                v-if="item.caption"
                class="text-menu/60 mt-1 text-sm"
            >
                {{ item.caption }}
            </figcaption>
        </figure>
    </div>
</template>
