<script lang="ts" setup>
import type { SectionInfo, SectionType } from '@/types/about';
import { ref } from 'vue';
import { Icon } from '..';

const props = defineProps<{
    // TODO: Typedef this thing
    sections: SectionType[];
}>();

const folder = ref<SectionInfo | null>(props.sections[0]?.info[0] ?? null);
const currentSection = ref<SectionType | null>(props.sections[0] ?? null);

const isSectionActive = (name: string) => {
    return currentSection.value?.name === name;
};

const focusCurrentSection = (section: SectionType) => {
    currentSection.value = section;
    folder.value = section.info[0];
};

const focusCurrentFolder = (f: SectionInfo) => {
    folder.value = f;
};

const isOpen = (f: string) => {
    return folder.value?.name === f;
};
</script>

<template>
    <div
        class="flex w-full flex-col lg:min-w-[275px] lg:max-w-[275px] lg:flex-row 2xl:min-w-[310px] 2xl:max-w-[310px]"
    >
        <div class="hidden h-full w-20 border-r border-r-navy lg:block">
            <div
                class="my-8 flex justify-center opacity-50 transition-all duration-200 hover:cursor-pointer hover:text-white hover:opacity-100 [&.active]:text-white [&.active]:opacity-100"
                v-for="section in sections"
                :key="section.name"
                :class="{ active: isSectionActive(section.name) }"
            >
                <Icon
                    :name="section.icon"
                    class="h-5 w-5"
                    @click="focusCurrentSection(section)"
                />
            </div>
        </div>
        <div class="hidden h-full w-full border-r border-r-navy lg:block">
            <div
                class="hidden min-h-7 w-full min-w-full items-center border-b border-b-navy px-4 py-3 text-sm text-white lg:flex"
            >
                <Icon name="caret-down" class="mr-4 h-3 w-3" />
                <p>{{ currentSection?.name }}</p>
            </div>
            <div>
                <div
                    v-for="(folder, key) in currentSection?.info"
                    :key="key"
                    class="text-menu-text mx-4 my-2 grid grid-cols-2 items-center"
                    @click="focusCurrentFolder(folder)"
                >
                    <div
                        class="col-span-2 flex items-center transition-all duration-200 hover:cursor-pointer hover:text-white"
                        :class="{ 'text-white': isOpen(folder.name) }"
                    >
                        <Icon
                            v-if="isOpen(folder.name)"
                            name="folder-open"
                            class="mr-3 h-4 w-4"
                        />
                        <Icon v-else name="folder" class="mr-3 h-4 w-4" />
                        <p
                            :id="folder.name"
                            v-html="folder.name"
                            :class="{ active: isOpen(folder.name) }"
                        ></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="flex h-full w-full">
        <div
            :key="folder?.name"
            data-aos="fade-left"
            class="relative flex h-full w-full flex-col"
        >
            <component :is="folder?.component" :content="folder?.content" />
        </div>
    </div>
</template>
