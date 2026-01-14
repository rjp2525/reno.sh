<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import { useCodeHighlighter } from '@/composables';
import { computed, onMounted, ref, watch } from 'vue';

export interface EducationItem {
    id: number;
    school_name: string;
    location: string | null;
    degree: string;
    minor: string | null;
    started: string;
    graduated: string | null;
    description: string | null;
    achievements: string[] | null;
    projects: string[] | null;
    extracurriculars: string[] | null;
}

const props = defineProps<{
    education: EducationItem[];
}>();

const { highlightCode, isLoading } = useCodeHighlighter({
    theme: 'github-dark',
});
const highlightedCode = ref('');

const codeBlock = computed(() => {
    let code = `<?php

namespace Reno\\Resume;

class Education
{
    public array $institutions = [];

    public function __construct()
    {
        $this->addEducation();
    }

    protected function addEducation(): void
    {`;

    props.education.forEach((edu) => {
        code += `
        $this->institutions[] = [
            'school' => '${edu.school_name}',
            'degree' => '${edu.degree}',
            'year' => '${edu.graduated ?? 'In Progress'}',
        ];
`;
    });

    code += `    }
}`;

    return code;
});

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
            <h3 class="text-menu px-8 pt-8">// Education</h3>
            <ScrollArea class="h-[calc(100vh-220px)] w-full p-8 pt-0">
                <div class="flex flex-col space-y-6">
                    <div
                        v-for="edu in education"
                        :key="edu.id"
                        class="space-y-2"
                    >
                        <h4 class="font-medium text-white">
                            {{ edu.school_name }}
                        </h4>
                        <p class="text-menu">{{ edu.degree }}</p>
                        <p v-if="edu.minor" class="text-menu/80">
                            Minor: {{ edu.minor }}
                        </p>
                        <p class="text-menu/60 text-xs">
                            {{ edu.started }} - {{ edu.graduated ?? 'Present' }}
                            <span v-if="edu.location">
                                · {{ edu.location }}</span
                            >
                        </p>
                        <p v-if="edu.description" class="text-menu mt-2">
                            {{ edu.description }}
                        </p>

                        <div v-if="edu.achievements?.length" class="mt-3">
                            <p
                                class="text-menu/80 mb-1 text-xs tracking-wide uppercase"
                            >
                                Achievements
                            </p>
                            <ul class="text-menu ml-4 list-disc">
                                <li
                                    v-for="(
                                        achievement, idx
                                    ) in edu.achievements"
                                    :key="idx"
                                >
                                    {{ achievement }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="education.length === 0" class="text-menu/60">
                        No education records available.
                    </div>
                </div>
            </ScrollArea>
        </div>
    </div>
</template>
