<script setup lang="ts">
import { SeoHead } from '@/components';
import { MobilePageHeader } from '@/components/page';
import type { TiptapContent } from '@/composables';
import { useAboutTabs } from '@/composables';
import { GuestLayout } from '@/layouts';
import { hobbies } from '@/routes/about';
import type { ContentBlock } from '@/types/blocks';
import { computed } from 'vue';
import { AboutSidebar, BlockRenderer } from './partials';

interface ContentPage {
    slug: string;
    title: string;
    icon: string | null;
    content: ContentBlock[] | TiptapContent | string | null;
}

const props = defineProps<{
    activeTab: string;
    tabs: Record<string, string>;
    content: ContentBlock[] | TiptapContent | string | null;
    pages: ContentPage[];
}>();

const { currentTab, switchTab } = useAboutTabs({
    activeTab: props.activeTab,
    tabs: props.tabs,
    routeFunction: (tab) => hobbies(tab),
});

const currentPageContent = computed(() => {
    const page = props.pages.find((p) => p.slug === currentTab.value);
    return page?.content ?? props.content;
});
</script>

<template>
    <GuestLayout>
        <SeoHead
            title="About Me - Hobbies"
            description="Discover Reno Philibert's hobbies including photography, hiking, snowboarding, and overlanding."
        />

        <main class="flex h-full w-full flex-auto flex-col overflow-hidden lg:flex-row">
            <MobilePageHeader> _about / hobbies </MobilePageHeader>
            <AboutSidebar
                current-section="hobbies"
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
                    <BlockRenderer :content="currentPageContent" />
                </div>
            </div>
        </main>
    </GuestLayout>
</template>
