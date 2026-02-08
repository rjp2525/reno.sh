<script setup lang="ts">
import type { ImageBlock } from '@/types/blocks';

defineProps<{
    data: ImageBlock['data'];
}>();
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
        <img
            :src="`/img/${data.image}`"
            :alt="data.caption ?? ''"
            loading="lazy"
            :class="[
                'rounded-lg',
                {
                    'inline-block max-w-md': data.alignment !== 'full',
                    'w-full': data.alignment === 'full',
                },
            ]"
        />
        <figcaption
            v-if="data.caption"
            class="text-menu/60 mt-2 text-sm"
        >
            {{ data.caption }}
        </figcaption>
    </figure>
</template>
