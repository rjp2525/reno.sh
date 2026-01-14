import { router } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';

export interface UseAboutTabsOptions {
    activeTab: string;
    tabs: Record<string, string>;
    routeFunction: (tab: string) => { url: string };
}

export function useAboutTabs(options: UseAboutTabsOptions) {
    const { activeTab: initialTab, tabs, routeFunction } = options;

    const currentTab = ref(initialTab);

    const tabList = computed(() =>
        Object.entries(tabs).map(([slug, title]) => ({
            slug,
            title,
            isActive: slug === currentTab.value,
        })),
    );

    function switchTab(tabSlug: string) {
        if (tabSlug === currentTab.value) return;

        currentTab.value = tabSlug;

        const route = routeFunction(tabSlug);
        router.visit(route.url, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function isTabActive(tabSlug: string): boolean {
        return currentTab.value === tabSlug;
    }

    return {
        currentTab,
        tabList,
        switchTab,
        isTabActive,
    };
}
