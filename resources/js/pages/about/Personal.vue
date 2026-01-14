<script setup lang="ts">
import { MobilePageHeader } from '@/components/page';
import type { TiptapContent } from '@/composables';
import { useAboutTabs } from '@/composables';
import { GuestLayout } from '@/layouts';
import { personal } from '@/routes/about';
import { computed } from 'vue';
import { AboutSidebar, TiptapRenderer } from './partials';

interface ContentPage {
    slug: string;
    title: string;
    icon: string | null;
    content: TiptapContent | string | null;
}

const props = defineProps<{
    activeTab: string;
    tabs: Record<string, string>;
    content: TiptapContent | string | null;
    pages: ContentPage[];
}>();

const { currentTab, switchTab } = useAboutTabs({
    activeTab: props.activeTab,
    tabs: props.tabs,
    routeFunction: (tab) => personal(tab),
});

const currentPageContent = computed(() => {
    const page = props.pages.find((p) => p.slug === currentTab.value);
    return page?.content ?? props.content;
});
</script>

<template>
    <GuestLayout>
        <Head title="About Me - Personal" />

        <main class="flex h-full w-full flex-auto overflow-hidden">
            <MobilePageHeader> _about / personal </MobilePageHeader>
            <AboutSidebar
                current-section="personal"
                :active-tab="currentTab"
                :tabs="tabs"
                @tab-change="switchTab"
            />
            <div class="flex h-full w-full">
                <div
                    :key="currentTab"
                    data-aos="fade-left"
                    class="relative flex h-full w-full flex-col"
                >
                    <TiptapRenderer :content="currentPageContent" />
                </div>
            </div>
        </main>
    </GuestLayout>
</template>
