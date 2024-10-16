<script lang="ts" setup>
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { ref } from 'vue';
import { Icon } from '..';

const props = defineProps<{
    experience: {
        title: string;
        company: string;
        tenure: string;
        start: string;
        end: string | null;
        location: string | null;
        responsibilities?: string[];
    };
    defaultOpen?: boolean;
}>();

const isOpen = ref(props.defaultOpen ?? false);
</script>

<template>
    <Collapsible v-model:open="isOpen">
        <CollapsibleTrigger
            class="flex w-full items-center justify-between transition-colors duration-100 hover:text-white"
            :class="{ 'font-medium text-white': isOpen }"
        >
            <span
                >{{ experience.title }} at {{ experience.company }} ({{
                    experience.start
                }}
                - {{ experience.end ?? 'Present' }})</span
            >
            <div class="relative">
                <Icon
                    name="plus"
                    class="absolute right-0 h-4 w-4 transition-all duration-200 ease-in-out"
                    :class="{
                        '!rotate-0 !scale-100': !isOpen,
                        '!invisible !rotate-90 !scale-0': isOpen,
                        '!invisible': isOpen,
                    }"
                />
                <Icon
                    name="minus"
                    class="absolute right-0 h-4 w-4 transition-all duration-200 ease-in-out"
                    :class="{
                        '!rotate-90 !scale-0': !isOpen,
                        '!rotate-0 !scale-100': isOpen,
                        '!invisible': !isOpen,
                    }"
                />
            </div>
        </CollapsibleTrigger>
        <CollapsibleContent>
            <div class="mt-2 flex w-full flex-col">
                <div
                    v-if="experience.location"
                    class="mb-2 ml-2 text-github-text"
                >
                    {{ experience.location }}
                </div>
                <ul class="ml-6 list-disc" v-if="experience.responsibilities">
                    <li
                        v-for="responsibility in experience.responsibilities"
                        :key="responsibility"
                    >
                        {{ responsibility }}
                    </li>
                </ul>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
