<script setup lang="ts">
import { AppFooter, AppHeader } from '@/components';
import AOS from 'aos';
import 'aos/dist/aos.css';
import { onBeforeUnmount, onMounted, provide, ref } from 'vue';

const isMobile = ref(false);

onMounted(() => {
    if (window.innerWidth <= 1024) isMobile.value = true;
    window.addEventListener('resize', handleResize);

    AOS.init({
        disable: false,
        startEvent: 'DOMContentLoaded',
        initClassName: 'aos-init',
        animatedClassName: 'aos-animate',
        useClassNames: false,
        disableMutationObserver: false,
        debounceDelay: 50,
        throttleDelay: 99,
        offset: 120,
        delay: 0,
        duration: 400,
        easing: 'ease',
        once: false,
        mirror: false,
        anchorPlacement: 'top-bottom',
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
});

const handleResize = () => {
    window.innerWidth <= 1024
        ? (isMobile.value = true)
        : (isMobile.value = false);
};

provide('isMobile', isMobile);
</script>

<template>
    <div
        class="flex h-full w-full flex-col justify-between rounded-md border border-navy"
    >
        <AppHeader />
        <div data-aos="fade-in" :key="$page.component" class="h-full">
            <slot />
        </div>
        <AppFooter />
    </div>
</template>
