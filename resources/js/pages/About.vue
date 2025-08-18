<script setup lang="ts">
import { MobilePageHeader, PageMenu } from '@/components/page';
import { GuestLayout } from '@/layouts';
import type { Section } from '@/types/about';
import { inject, markRaw, ref } from 'vue';
import { DynamicContent } from './partials';

const isMobile = inject('isMobile', ref(false));

const props = defineProps<{
    sections: Section[];
}>();

const transformedSections = props.sections.map((section) => ({
    name: section.name,
    icon: section.icon,
    info: section.pages.map((page) => ({
        name: page.slug,
        component: markRaw(DynamicContent),
        content: page.content,
        type: page.type,
    })),
}));
</script>

<template>
    <GuestLayout>
        <Head title="About Me" />

        <main class="flex h-full w-full flex-auto overflow-hidden">
            <MobilePageHeader> _about </MobilePageHeader>
            <PageMenu :sections="transformedSections" />
        </main>
    </GuestLayout>
</template>

<style lang="scss" scoped>
.custom-gradient-blue {
    @apply fixed bottom-1/4 right-[5%] z-10 h-[300px] w-[300px] opacity-50;
    border-radius: 0% 0% 50% 50%;
    filter: blur(70px);
    background: radial-gradient(
        circle at 50% 50%,
        rgba(77, 91, 206, 1),
        rgba(76, 0, 255, 0)
    );
}

.custom-gradient-green {
    @apply absolute right-[30%] top-[20%] z-10 h-[300px] w-[300px] opacity-50;
    border-radius: 0% 50% 0% 50%;
    filter: blur(70px);
    background: radial-gradient(
        circle at 50% 50%,
        rgba(67, 217, 173, 1),
        rgba(76, 0, 255, 0)
    );
}
</style>
