<script setup lang="ts">
/**
 * Emits `load` when it scrolls into view (or is already in view), for infinite lists.
 * While `loading` is true it waits; once loading ends it fires again if it's still on
 * screen, so a short page doesn't leave the list stuck.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ loading?: boolean; rootMargin?: string }>(), {
    loading: false,
    rootMargin: '600px',
});

const emit = defineEmits<{ load: [] }>();

const el = ref<HTMLElement | null>(null);
const visible = ref(false);
let observer: IntersectionObserver | null = null;

const maybeLoad = () => {
    if (visible.value && !props.loading) emit('load');
};

onMounted(() => {
    observer = new IntersectionObserver(
        ([entry]) => {
            visible.value = entry.isIntersecting;
            maybeLoad();
        },
        { rootMargin: props.rootMargin },
    );
    if (el.value) observer.observe(el.value);
});

watch(
    () => props.loading,
    (loading) => {
        if (!loading) requestAnimationFrame(maybeLoad);
    },
);

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="el">
        <slot />
    </div>
</template>
