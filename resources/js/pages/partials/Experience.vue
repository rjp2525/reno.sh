<script lang="ts" setup>
import MonoCollapsible from '@/components/page/MonoCollapsible.vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { ExperienceItem } from '@/types/about';
import VCodeBlock from '@wdns/vue-code-block';
import { computed } from 'vue';

const props = defineProps<{
    content: ExperienceItem[];
}>();

const generateCodeBlock = (experience: ExperienceItem[]) => {
    let codeString = `<?php

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
    let count = experience.length;
    experience.forEach((job, index) => {
        codeString += `
        $this->experience[] = new Job(
            title: '${job.title}',
            company: '${job.company}',
            tenure: '${job.start} - ${job.end ?? 'Present'}'
        );${count === index + 1 ? '' : '\n'}`;
    });

    codeString += `
    }

    protected function addEducation(): void
    {
        $this->education[] = new College(
            name: 'Southern New Hampshire University',
            degree: 'Bachelor of Science - BS, Computer Science',
            year: '2019'
        );
    }
}
`;

    return codeString;
};

const code = computed(() => generateCodeBlock(props.content));
</script>

<template>
    <div class="relative flex h-full w-full flex-col lg:grid lg:grid-cols-2">
        <div>
            <ScrollArea class="h-[calc(100vh-150px)] w-full p-6">
                <VCodeBlock
                    :code="code"
                    highlightjs
                    lang="php"
                    theme="night-owl"
                    :copy-button="false"
                />
            </ScrollArea>
        </div>
        <div class="space-y-4 border-l border-l-navy text-sm">
            <h3 class="px-8 pt-8">// Experience</h3>
            <ScrollArea class="h-[calc(100vh-220px)] w-full p-8 pt-0">
                <div class="flex flex-col space-y-3">
                    <MonoCollapsible
                        v-for="(job, key) in content"
                        :key="job.id"
                        :experience="job"
                        :default-open="key === 0"
                    />
                </div>
            </ScrollArea>
        </div>
    </div>
</template>

<style lang="scss">
code.language-php.hljs {
    background: transparent !important;
}
</style>
