<script setup lang="ts">
import {
    Camera,
    ChevronLeft,
    ChevronRight,
    Instagram,
    Star,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps<{
    photo: Photo | null;
    photos: Photo[];
}>();

const emit = defineEmits<{
    close: [];
    navigate: [photo: Photo];
}>();

const currentIndex = computed(() => {
    if (!props.photo) return -1;
    return props.photos.findIndex((p) => p.id === props.photo?.id);
});

const canGoPrev = computed(() => currentIndex.value > 0);
const canGoNext = computed(() => currentIndex.value < props.photos.length - 1);

const goPrev = () => {
    if (canGoPrev.value) {
        emit('navigate', props.photos[currentIndex.value - 1]);
    }
};

const goNext = () => {
    if (canGoNext.value) {
        emit('navigate', props.photos[currentIndex.value + 1]);
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if (!props.photo) return;

    switch (e.key) {
        case 'Escape':
            emit('close');
            break;
        case 'ArrowLeft':
            goPrev();
            break;
        case 'ArrowRight':
            goNext();
            break;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

watch(
    () => props.photo,
    (photo) => {
        if (photo) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    },
    { immediate: true },
);
</script>

<template>
    <Teleport to="body">
        <Transition name="lightbox">
            <div
                v-if="photo"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/95"
                @click.self="emit('close')"
            >
                <button
                    class="absolute top-4 right-4 z-10 rounded-full bg-white/10 p-2 text-white transition-colors hover:bg-white/20"
                    @click="emit('close')"
                >
                    <X class="h-6 w-6" />
                </button>

                <button
                    v-if="canGoPrev"
                    class="absolute left-4 z-10 rounded-full bg-white/10 p-3 text-white transition-colors hover:bg-white/20"
                    @click="goPrev"
                >
                    <ChevronLeft class="h-8 w-8" />
                </button>

                <button
                    v-if="canGoNext"
                    class="absolute right-4 z-10 rounded-full bg-white/10 p-3 text-white transition-colors hover:bg-white/20"
                    @click="goNext"
                >
                    <ChevronRight class="h-8 w-8" />
                </button>

                <div class="flex h-full w-full max-w-7xl flex-col p-4 md:p-8">
                    <div
                        class="relative flex flex-1 items-center justify-center overflow-hidden"
                    >
                        <img
                            v-if="photo.web_url"
                            :src="photo.web_url"
                            :alt="photo.title"
                            class="max-h-full max-w-full object-contain"
                        />
                        <div
                            v-else
                            class="flex h-64 w-full items-center justify-center rounded-lg bg-gray-800"
                        >
                            <p class="text-gray-400">Image not available</p>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-4 rounded-lg bg-gray-900/80 p-4 md:flex-row md:items-start md:justify-between"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <h2
                                    class="text-xl font-semibold text-white md:text-2xl"
                                >
                                    {{ photo.title }}
                                </h2>
                                <Star
                                    v-if="photo.is_favorite"
                                    class="h-5 w-5 fill-yellow-400 text-yellow-400"
                                />
                            </div>

                            <div
                                v-if="photo.category"
                                class="inline-block rounded-full px-3 py-1 text-sm"
                                :style="
                                    photo.category.color
                                        ? {
                                              backgroundColor:
                                                  photo.category.color + '20',
                                              color: photo.category.color,
                                          }
                                        : {}
                                "
                                :class="
                                    !photo.category.color
                                        ? 'bg-gray-700 text-gray-300'
                                        : ''
                                "
                            >
                                {{ photo.category.name }}
                            </div>

                            <p
                                v-if="photo.description"
                                class="max-w-xl text-sm text-gray-300"
                            >
                                {{ photo.description }}
                            </p>

                            <div
                                v-if="photo.tags.length"
                                class="flex flex-wrap gap-1"
                            >
                                <span
                                    v-for="tag in photo.tags"
                                    :key="tag.id"
                                    class="rounded-full bg-gray-700 px-2 py-0.5 text-xs text-gray-300"
                                >
                                    {{ tag.name }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3 text-sm text-gray-400">
                            <div
                                v-if="
                                    photo.exif.camera ||
                                    photo.exif.lens ||
                                    photo.exif.focal_length
                                "
                                class="space-y-1"
                            >
                                <div class="flex items-center gap-2">
                                    <Camera
                                        class="h-4 w-4 shrink-0 text-gray-500"
                                    />
                                    <span v-if="photo.exif.camera">{{
                                        photo.exif.camera
                                    }}</span>
                                </div>
                                <div
                                    v-if="photo.exif.lens"
                                    class="ml-6 text-xs text-gray-500"
                                >
                                    {{ photo.exif.lens }}
                                </div>
                            </div>

                            <div
                                v-if="photo.exif.summary"
                                class="font-mono text-xs text-gray-400"
                            >
                                {{ photo.exif.summary }}
                            </div>

                            <div v-if="photo.taken_at" class="text-gray-500">
                                {{ photo.taken_at }}
                            </div>

                            <a
                                v-if="photo.instagram_link"
                                :href="photo.instagram_link"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 text-pink-400 transition-colors hover:text-pink-300"
                            >
                                <Instagram class="h-4 w-4" />
                                <span>View on Instagram</span>
                            </a>
                        </div>
                    </div>

                    <div class="mt-2 text-center text-sm text-gray-500">
                        {{ currentIndex + 1 }} / {{ photos.length }}
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.lightbox-enter-active,
.lightbox-leave-active {
    transition: opacity 0.3s ease;
}

.lightbox-enter-from,
.lightbox-leave-to {
    opacity: 0;
}
</style>
