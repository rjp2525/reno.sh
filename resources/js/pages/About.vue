<script setup lang="ts">
import { MobilePageHeader, PageMenu } from '@/components/page';
import { GuestLayout } from '@/layouts';
import type { ExperienceItem, SkillCategory } from '@/types/about';
import { inject, markRaw, ref } from 'vue';
import {
    Biography,
    Experience,
    Hiking,
    Maya,
    Overlanding,
    Photography,
    Skills,
    Snowboarding,
    Software,
} from './partials';

const isMobile = inject('isMobile', ref(false));

interface PageBuilderContent {
    type: string;
    data: {
        content: string;
    };
}

interface AboutPageSection {
    id: number;
    title: string;
    slug: string;
    section: string;
    content: PageBuilderContent[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    experience: ExperienceItem[];
    skills: SkillCategory[];
    pages: AboutPageSection[];
}>();

const sections = [
    {
        name: 'professional',
        icon: 'code-browser',
        info: [
            {
                name: 'experience',
                component: markRaw(Experience),
                content: props.experience,
            },
            {
                name: 'skills',
                component: markRaw(Skills),
                content: props.skills,
            },
        ],
    },
    {
        name: 'personal',
        icon: 'person',
        info: [
            {
                name: 'bio',
                component: markRaw(Biography),
                content: props.pages.find((page) => page.slug === 'bio')
                    ?.content,
            },
            {
                name: 'maya',
                component: markRaw(Maya),
                content: props.pages.find((page) => page.slug === 'maya')
                    ?.content,
            },
        ],
    },
    {
        name: 'hobbies',
        icon: 'hobbies',
        info: [
            {
                name: 'overlanding',
                component: markRaw(Overlanding),
                content: props.pages.find((page) => page.slug === 'overlanding')
                    ?.content,
            },
            {
                name: 'photography',
                component: markRaw(Photography),
                content: props.pages.find((page) => page.slug === 'photography')
                    ?.content,
            },
            {
                name: 'snowboarding',
                component: markRaw(Snowboarding),
                content: props.pages.find(
                    (page) => page.slug === 'snowboarding',
                )?.content,
            },
            {
                name: 'hiking',
                component: markRaw(Hiking),
                content: props.pages.find((page) => page.slug === 'hiking')
                    ?.content,
            },
            {
                name: 'software',
                component: markRaw(Software),
                content: props.pages.find((page) => page.slug === 'software')
                    ?.content,
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
