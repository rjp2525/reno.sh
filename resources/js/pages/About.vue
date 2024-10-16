<script setup lang="ts">
import { MobilePageHeader, PageMenu } from '@/components/page';
import { GuestLayout } from '@/layouts';
import { inject, ref } from 'vue';
import { Experience, Skills } from './partials';

const isMobile = inject('isMobile', ref(false));

const sections = [
    {
        name: 'professional',
        icon: 'code-browser',
        info: [
            {
                name: 'experience',
                component: Experience,
                content: 'professional: experience',
            },
            {
                name: 'skills',
                component: Skills,
                content: 'professional: skills',
            },
        ],
    },
    {
        name: 'personal',
        icon: 'person',
        info: [
            {
                name: 'bio',
                content: 'personal: bio',
            },
            {
                name: 'education',
                content: 'personal: education',
            },
            {
                name: 'maya',
                content: 'personal: maya',
            },
        ],
    },
    {
        name: 'hobbies',
        icon: 'hobbies',
        info: [
            {
                name: 'overlanding',
                content: 'hobby: overlanding',
            },
            {
                name: 'photography',
                content: 'hobby: photography',
            },
            {
                name: 'snowboarding',
                content: 'hobby: snowboarding',
            },
            {
                name: 'hiking',
                content: 'hobby: hiking',
            },
            {
                name: 'software',
                content: 'hobby: software',
            },
        ],
    },
];
</script>

<template>
    <GuestLayout>
        <Head title="About Me" />

        <main class="flex h-full w-full flex-auto overflow-hidden">
            <MobilePageHeader> _about </MobilePageHeader>
            <PageMenu :sections="sections" />
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
