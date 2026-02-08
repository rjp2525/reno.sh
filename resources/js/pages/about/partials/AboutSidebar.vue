<script setup lang="ts">
import { Icon } from '@/components';
import { hobbies, personal, professional } from '@/routes/about';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface SectionNav {
    name: string;
    icon: string;
    route: () => { url: string };
}

const props = defineProps<{
    currentSection: 'professional' | 'personal' | 'hobbies';
    activeTab: string;
    tabs: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: 'tab-change', tab: string): void;
}>();

const sections: SectionNav[] = [
    { name: 'professional', icon: 'code-browser', route: () => professional() },
    { name: 'personal', icon: 'person', route: () => personal() },
    { name: 'hobbies', icon: 'hobbies', route: () => hobbies() },
];

const tabList = computed(() =>
    Object.entries(props.tabs).map(([slug, title]) => ({
        slug,
        title,
        isActive: slug === props.activeTab,
    })),
);

function navigateToSection(section: SectionNav) {
    if (section.name === props.currentSection) return;
    router.visit(section.route().url);
}

function selectTab(slug: string) {
    emit('tab-change', slug);
}

function isSectionActive(name: string): boolean {
    return props.currentSection === name;
}
</script>

<template>
    <div
        class="flex shrink-0 flex-col lg:w-full lg:max-w-[275px] lg:min-w-[275px] lg:flex-row 2xl:max-w-[310px] 2xl:min-w-[310px]"
    >
        <!-- Mobile section & tab navigation -->
        <div class="border-b-navy space-y-2 border-b px-4 py-3 lg:hidden">
            <div class="flex gap-1 overflow-x-auto">
                <button
                    v-for="section in sections"
                    :key="section.name"
                    @click="navigateToSection(section)"
                    class="flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-xs transition-colors"
                    :class="isSectionActive(section.name)
                        ? 'bg-[#1E2D3D] text-white'
                        : 'text-menu hover:text-white'"
                >
                    {{ section.name }}
                </button>
            </div>
            <div v-if="tabList.length > 1" class="flex gap-1 overflow-x-auto">
                <button
                    v-for="tab in tabList"
                    :key="tab.slug"
                    @click="selectTab(tab.slug)"
                    class="flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1 text-xs transition-colors"
                    :class="tab.isActive
                        ? 'bg-[#1E2D3D]/60 text-white'
                        : 'text-menu/70 hover:text-white'"
                >
                    {{ tab.title }}
                </button>
            </div>
        </div>

        <!-- Desktop sidebar -->
        <div class="border-r-navy hidden h-full w-20 border-r lg:block">
            <div
                v-for="section in sections"
                :key="section.name"
                class="my-8 flex justify-center opacity-50 transition-all duration-200 hover:cursor-pointer hover:text-white hover:opacity-100"
                :class="{
                    '!text-white !opacity-100': isSectionActive(section.name),
                }"
                @click="navigateToSection(section)"
            >
                <Icon :name="section.icon" class="h-5 w-5" />
            </div>
        </div>

        <div class="border-r-navy hidden h-full w-full border-r lg:block">
            <div
                class="border-b-navy hidden min-h-7 w-full min-w-full items-center border-b px-4 py-3 text-sm text-white lg:flex"
            >
                <Icon name="caret-down" class="mr-4 h-3 w-3" />
                <p>{{ currentSection }}</p>
            </div>
            <div>
                <div
                    v-for="tab in tabList"
                    :key="tab.slug"
                    class="text-menu-text mx-4 my-2 grid grid-cols-2 items-center"
                    @click="selectTab(tab.slug)"
                >
                    <div
                        class="col-span-2 flex items-center transition-all duration-200 hover:cursor-pointer hover:text-white"
                        :class="{ 'text-white': tab.isActive }"
                    >
                        <Icon
                            v-if="tab.isActive"
                            name="folder-open"
                            class="mr-3 h-4 w-4"
                        />
                        <Icon v-else name="folder" class="mr-3 h-4 w-4" />
                        <p :class="{ active: tab.isActive }">
                            {{ tab.title }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
