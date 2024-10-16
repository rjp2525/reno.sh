<script lang="ts" setup>
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { SkillCategory } from '@/types/about';
import { ref } from 'vue';
import { Icon } from '..';

const props = defineProps<{
    category: SkillCategory;
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
            <span>{{ category.title }}</span>
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
            <div class="mt-2 w-full">
                <ul class="ml-6 list-disc">
                    <li v-for="skill in category.skills" :key="skill.name">
                        {{ skill.name }} ({{ skill.level }} -
                        {{ skill.experience }})
                    </li>
                </ul>
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
