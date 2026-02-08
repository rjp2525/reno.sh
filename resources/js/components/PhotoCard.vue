<script setup lang="ts">
import { Instagram, Star } from 'lucide-vue-next';

const props = defineProps<{
    photo: Photo;
}>();

const emit = defineEmits<{
    click: [photo: Photo];
}>();
</script>

<template>
    <div
        class="group relative cursor-pointer overflow-hidden rounded-lg bg-gray-800"
        @click="emit('click', photo)"
    >
        <div class="aspect-[4/3] overflow-hidden">
            <img
                v-if="photo.thumbnail_url"
                :src="photo.thumbnail_url"
                :alt="photo.title"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                loading="lazy"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gray-700"
            >
                <svg
                    class="h-12 w-12 text-gray-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </div>
        </div>

        <div
            class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/80 via-black/20 to-transparent p-4 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
        >
            <div class="space-y-1">
                <h3 class="line-clamp-1 font-medium text-white">
                    {{ photo.title }}
                </h3>
                <p
                    v-if="photo.category"
                    class="text-sm"
                    :style="
                        photo.category.color
                            ? { color: photo.category.color }
                            : {}
                    "
                >
                    {{ photo.category.name }}
                </p>
                <p
                    v-if="photo.exif.summary"
                    class="line-clamp-1 text-xs text-gray-300"
                >
                    {{ photo.exif.summary }}
                </p>
            </div>
        </div>

        <div
            v-if="photo.is_favorite"
            class="absolute top-2 right-2 rounded-full bg-black/50 p-1"
        >
            <Star class="h-4 w-4 fill-yellow-400 text-yellow-400" />
        </div>

        <a
            v-if="photo.instagram_link"
            :href="photo.instagram_link"
            target="_blank"
            rel="noopener noreferrer"
            class="absolute top-2 left-2 rounded-full bg-black/50 p-1.5 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-black/70"
            @click.stop
        >
            <Instagram class="h-4 w-4 text-white" />
        </a>
    </div>
</template>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
