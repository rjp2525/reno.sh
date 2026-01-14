<script setup lang="ts">
import { Icon } from '@/components';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useCodeHighlighter } from '@/composables';
import { computed, onMounted, ref, watch } from 'vue';

export interface ExperienceItem {
    id: number;
    title: string;
    company: string;
    location: string | null;
    start: string;
    end: string | null;
    responsibilities: string[];
    tenure: string;
}

const props = defineProps<{
    experience: ExperienceItem[];
}>();

const { highlightCode, isLoading } = useCodeHighlighter({
    theme: 'github-dark',
});
const highlightedCode = ref('');

const codeBlock = computed(() => {
    let code = `<?php

namespace Reno\\Resume;

use Education\\College;
use Former\\Job;

class Resume implements Life
{
    public function __construct(
        public string $name = "Reno Philibert",
        public string $title = "Full Stack Software Engineer",
        public array $experience = [],
        public array $education = []
    ) {
        $this->addExperience();
        $this->addEducation();
    }

    protected function addExperience(): void
    {`;

    props.experience.forEach((job) => {
        code += `
        $this->experience[] = new Job(
            title: '${job.title}',
            company: '${job.company}',
            tenure: '${job.start} - ${job.end ?? 'Present'}'
        );
`;
    });

    code += `    }

    protected function addEducation(): void
    {
        $this->education[] = new College(
            name: 'Southern New Hampshire University',
            degree: 'Bachelor of Science - BS, Computer Science',
            year: '2019'
        );
    }
}`;

    return code;
});

const openItems = ref<Set<number>>(new Set([0]));

function toggleItem(index: number) {
    if (openItems.value.has(index)) {
        openItems.value.delete(index);
    } else {
        openItems.value.add(index);
    }
}

function isOpen(index: number): boolean {
    return openItems.value.has(index);
}

onMounted(async () => {
    highlightedCode.value = await highlightCode(codeBlock.value, 'php');
});

watch(codeBlock, async (newCode) => {
    highlightedCode.value = await highlightCode(newCode, 'php');
});
</script>

<template>
    <div class="relative flex h-full w-full flex-col lg:grid lg:grid-cols-2">
        <div>
            <ScrollArea class="h-[calc(100vh-150px)] w-full p-6">
                <div
                    v-if="isLoading"
                    class="flex items-center justify-center p-8"
                >
                    <span class="text-menu/60">Loading...</span>
                </div>
                <div
                    v-else
                    class="shiki-container [&_code]:!bg-transparent [&_pre]:!bg-transparent"
                    v-html="highlightedCode"
                />
            </ScrollArea>
        </div>
        <div class="border-l-navy space-y-4 border-l text-sm">
            <h3 class="text-menu px-8 pt-8">// Experience</h3>
            <ScrollArea class="h-[calc(100vh-220px)] w-full p-8 pt-0">
                <div class="flex flex-col space-y-3">
                    <Collapsible
                        v-for="(job, index) in experience"
                        :key="job.id"
                        :open="isOpen(index)"
                        @update:open="toggleItem(index)"
                    >
                        <CollapsibleTrigger
                            class="text-menu flex w-full items-center justify-between transition-colors duration-100 hover:text-white"
                            :class="{ 'font-medium text-white': isOpen(index) }"
                        >
                            <span>
                                {{ job.title }} at {{ job.company }} ({{
                                    job.start
                                }}
                                - {{ job.end ?? 'Present' }})
                            </span>
                            <div class="relative">
                                <Icon
                                    name="plus"
                                    class="absolute right-0 h-4 w-4 transition-all duration-200 ease-in-out"
                                    :class="{
                                        '!scale-100 !rotate-0': !isOpen(index),
                                        '!invisible !scale-0 !rotate-90':
                                            isOpen(index),
                                    }"
                                />
                                <Icon
                                    name="minus"
                                    class="absolute right-0 h-4 w-4 transition-all duration-200 ease-in-out"
                                    :class="{
                                        '!scale-0 !rotate-90': !isOpen(index),
                                        '!scale-100 !rotate-0': isOpen(index),
                                    }"
                                />
                            </div>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="mt-2 flex w-full flex-col">
                                <div
                                    v-if="job.location"
                                    class="text-github-text mb-2 ml-2"
                                >
                                    {{ job.location }}
                                </div>
                                <ul
                                    class="text-menu ml-6 list-disc"
                                    v-if="job.responsibilities"
                                >
                                    <li
                                        v-for="(
                                            responsibility, rIndex
                                        ) in job.responsibilities"
                                        :key="rIndex"
                                    >
                                        {{ responsibility }}
                                    </li>
                                </ul>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </ScrollArea>
        </div>
    </div>
</template>
