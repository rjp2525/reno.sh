<script lang="ts" setup>
import { MonoSkillCollapsible } from '@/components/page';
import MonoCollapsible from '@/components/page/MonoCollapsible.vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { ExperienceItem, SkillCategory } from '@/types/about';
import VCodeBlock from '@wdns/vue-code-block';
import { computed } from 'vue';

interface PageBuilderContent {
    type: string;
    data: {
        column_span?: number | string;
        content: string;
    };
}

interface ContentProps {
    content:
        | PageBuilderContent[]
        | ExperienceItem[]
        | SkillCategory[]
        | null
        | undefined;
    type?: string;
}

const props = defineProps<ContentProps>();

const generateExperienceCodeBlock = (experience: ExperienceItem[]) => {
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

    private function addExperience(): void
    {`;

    experience.forEach((exp) => {
        codeString += `
        $this->experience[] = new Job(
            title: "${exp.title}",
            company: "${exp.company}",
            location: "${exp.location}",
            start: "${exp.start}",
            end: "${exp.end}",
        );`;
    });

    codeString += `
    }
}`;

    return codeString;
};

const generateSkillsCodeBlock = (categories: SkillCategory[]) => {
    let codeString = `interface Skill {
  name: string;
  level: string;
  experience: string;
}

interface Category {
  title: string;
  skills: Skill[];
}

class Skills {
  public categories: Category[];

  constructor() {
    this.categories = [`;

    let count = categories.length;
    categories.forEach((category, index) => {
        codeString += `
      {
        title: "${category.title}",
        skills: [`;

        let skillCount = category.skills.length;
        category.skills.forEach((skill, skillIndex) => {
            codeString += `
          {
            name: "${skill.name}",
            level: "${skill.level}",
            experience: "${skill.experience}"
          }`;
            if (skillIndex < skillCount - 1) {
                codeString += ',';
            }
        });

        codeString += `
        ]
      }`;
        if (index < count - 1) {
            codeString += ',';
        }
    });

    codeString += `
    ];
  }
}`;

    return codeString;
};

const experienceCodeBlock = computed(() => {
    if (props.type === 'experience' && props.content) {
        return generateExperienceCodeBlock(props.content as ExperienceItem[]);
    }
    return '';
});

const skillsCodeBlock = computed(() => {
    if (props.type === 'skills' && props.content) {
        return generateSkillsCodeBlock(props.content as SkillCategory[]);
    }
    return '';
});

const isPageBuilderContent = computed(
    () =>
        Array.isArray(props.content) &&
        props.content.length > 0 &&
        typeof props.content[0] === 'object' &&
        'type' in props.content[0] &&
        'data' in props.content[0],
);
</script>

<template>
    <ScrollArea class="h-[calc(100vh-150px)] w-full">
        <div v-if="type === 'experience'" class="p-12">
            <VCodeBlock
                :code="experienceCodeBlock"
                lang="php"
                theme="github-dark"
                :show-line-numbers="true"
            />
            <div class="mt-8 space-y-4">
                <MonoCollapsible
                    v-for="exp in content as ExperienceItem[]"
                    :key="exp.id"
                    :experience="exp"
                />
            </div>
        </div>

        <div v-else-if="type === 'skills'" class="p-12">
            <VCodeBlock
                :code="skillsCodeBlock"
                lang="typescript"
                theme="github-dark"
                :show-line-numbers="true"
            />
            <div class="mt-8 space-y-4">
                <MonoSkillCollapsible
                    v-for="category in content as SkillCategory[]"
                    :key="category.title"
                    :category="category"
                />
            </div>
        </div>

        <div
            v-else-if="isPageBuilderContent"
            class="w-full max-w-full px-12 py-8 text-sm leading-tight text-menu"
        >
            <div
                v-if="
                    (content as PageBuilderContent[]).some(
                        (section) => section.data.column_span,
                    )
                "
                class="grid grid-cols-1 gap-4 lg:grid-cols-12"
            >
                <div
                    v-for="section in content as PageBuilderContent[]"
                    :key="section.data.content"
                    class="relative col-span-1 flex h-full items-center justify-center"
                    :class="`lg:col-span-${section.data.column_span ?? '12'}`"
                >
                    <div
                        v-if="section.type === 'richtext'"
                        class="prose prose-headings:mt-4 prose-headings:text-slate-300 prose-a:text-slate-400 prose-a:transition-all prose-a:duration-200 prose-a:hover:text-slate-200 prose-blockquote:border-navy prose-blockquote:text-menu prose-p:text-menu prose-li:text-menu prose-strong:text-slate-300 w-full max-w-full text-menu"
                        v-html="section.data.content"
                    ></div>
                    <div
                        v-else-if="section.type === 'image'"
                        class="flex h-full items-center justify-center"
                    >
                        <img
                            :src="'/storage/' + section.data.content"
                            class="h-96 w-96 rounded border border-navy object-cover"
                        />
                    </div>
                </div>
            </div>
            <div
                v-else
                class="prose prose-headings:mt-4 prose-headings:text-slate-300 prose-a:text-slate-400 prose-a:transition-all prose-a:duration-200 prose-a:hover:text-slate-200 prose-blockquote:border-navy prose-blockquote:text-menu prose-p:text-menu prose-li:text-menu prose-strong:text-slate-300 w-full max-w-full text-menu"
            >
                <div
                    v-for="section in content as PageBuilderContent[]"
                    :key="section.data.content"
                >
                    <div
                        v-if="section.type === 'richtext'"
                        v-html="section.data.content"
                    ></div>
                    <div
                        v-else-if="section.type === 'image'"
                        class="my-6 flex justify-center"
                    >
                        <img
                            :src="'/storage/' + section.data.content"
                            class="h-96 w-96 rounded border border-navy object-cover"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex h-full items-center justify-center p-12">
            <p class="text-menu/60">No content available</p>
        </div>
    </ScrollArea>
</template>
