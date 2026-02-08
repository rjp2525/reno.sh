<script setup lang="ts">
import { SeoHead } from '@/components';
import { MobilePageHeader } from '@/components/page';
import { useAboutTabs } from '@/composables';
import { GuestLayout } from '@/layouts';
import { professional } from '@/routes/about';
import { computed } from 'vue';
import {
    AboutSidebar,
    EducationTab,
    ExperienceTab,
    SkillsTab,
} from './partials';
import type { EducationItem } from './partials/EducationTab.vue';
import type { ExperienceItem } from './partials/ExperienceTab.vue';
import type { SkillCategory } from './partials/SkillsTab.vue';

const props = defineProps<{
    activeTab: string;
    tabs: Record<string, string>;
    experience: ExperienceItem[];
    skills: SkillCategory[];
    education: EducationItem[];
}>();

const { currentTab, switchTab } = useAboutTabs({
    activeTab: props.activeTab,
    tabs: props.tabs,
    routeFunction: (tab) => professional(tab),
});

const currentComponent = computed(() => {
    switch (currentTab.value) {
        case 'experience':
            return ExperienceTab;
        case 'skills':
            return SkillsTab;
        case 'education':
            return EducationTab;
        default:
            return ExperienceTab;
    }
});

const componentProps = computed(() => {
    switch (currentTab.value) {
        case 'experience':
            return { experience: props.experience };
        case 'skills':
            return { skills: props.skills };
        case 'education':
            return { education: props.education };
        default:
            return { experience: props.experience };
    }
});
</script>

<template>
    <GuestLayout>
        <SeoHead
            title="About Me - Professional"
            description="Learn about Reno Philibert's professional experience, technical skills, and education in software engineering."
        />

        <main class="flex h-full w-full flex-auto flex-col overflow-hidden lg:flex-row">
            <MobilePageHeader> _about / professional </MobilePageHeader>
            <AboutSidebar
                current-section="professional"
                :active-tab="currentTab"
                :tabs="tabs"
                @tab-change="switchTab"
            />
            <div class="flex h-full min-h-0 w-full flex-1">
                <div
                    :key="currentTab"
                    data-aos="fade-left"
                    class="relative flex h-full w-full flex-col"
                >
                    <component :is="currentComponent" v-bind="componentProps" />
                </div>
            </div>
        </main>
    </GuestLayout>
</template>
