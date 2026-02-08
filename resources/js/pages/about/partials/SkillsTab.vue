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

export interface Skill {
    id: number;
    category: string;
    name: string;
    level: string;
    experience: string;
}

export interface SkillCategory {
    title: string;
    skills: Skill[];
}

const props = defineProps<{
    skills: SkillCategory[];
}>();

const { highlightCode, isLoading } = useCodeHighlighter({
    theme: 'github-dark',
});
const highlightedCode = ref('');

const codeBlock = computed(() => {
    let code = `interface Skill {
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

    props.skills.forEach((category, catIndex) => {
        code += `
      {
        title: "${category.title}",
        skills: [`;

        category.skills.forEach((skill, skillIndex) => {
            code += `
          {
            name: "${skill.name}",
            level: "${skill.level}",
            experience: "${skill.experience}"
          }`;
            if (skillIndex < category.skills.length - 1) {
                code += ',';
            }
        });

        code += `
        ]
      }`;
        if (catIndex < props.skills.length - 1) {
            code += ',';
        }
    });

    code += `
    ];
  }
}`;

    return code;
});

const openCategories = ref<Set<string>>(new Set([props.skills[0]?.title]));

function toggleCategory(title: string) {
    if (openCategories.value.has(title)) {
        openCategories.value.delete(title);
    } else {
        openCategories.value.add(title);
    }
}

function isCategoryOpen(title: string): boolean {
    return openCategories.value.has(title);
}

onMounted(async () => {
    highlightedCode.value = await highlightCode(codeBlock.value, 'typescript');
});

watch(codeBlock, async (newCode) => {
    highlightedCode.value = await highlightCode(newCode, 'typescript');
});
</script>

<template>
    <div class="relative flex h-full w-full flex-col lg:grid lg:grid-cols-2">
        <div class="hidden lg:block">
            <ScrollArea class="h-[calc(100vh-150px)] w-full p-6">
                <div
                    v-if="isLoading"
                    class="flex items-center justify-center p-8"
                >
                    <span class="text-menu/60">Loading...</span>
                </div>
                <div
                    v-else
                    class="shiki-container overflow-x-auto [&_code]:!bg-transparent [&_pre]:!bg-transparent"
                    v-html="highlightedCode"
                />
            </ScrollArea>
        </div>
        <div class="space-y-4 text-sm lg:border-l lg:border-l-navy">
            <h3 class="text-menu px-4 pt-4 lg:px-8 lg:pt-8">// Skills</h3>
            <ScrollArea class="h-[calc(100vh-180px)] w-full p-4 pt-0 lg:h-[calc(100vh-220px)] lg:p-8 lg:pt-0">
                <div class="flex flex-col space-y-3">
                    <Collapsible
                        v-for="category in skills"
                        :key="category.title"
                        :open="isCategoryOpen(category.title)"
                        @update:open="toggleCategory(category.title)"
                    >
                        <CollapsibleTrigger
                            class="text-menu flex w-full items-center justify-between text-left transition-colors duration-100 hover:text-white"
                            :class="{
                                'font-medium text-white': isCategoryOpen(
                                    category.title,
                                ),
                            }"
                        >
                            <span
                                >{{ category.title }} ({{
                                    category.skills.length
                                }})</span
                            >
                            <div class="relative">
                                <Icon
                                    name="plus"
                                    class="absolute right-0 h-4 w-4 transition-all duration-200 ease-in-out"
                                    :class="{
                                        '!scale-100 !rotate-0': !isCategoryOpen(
                                            category.title,
                                        ),
                                        '!invisible !scale-0 !rotate-90':
                                            isCategoryOpen(category.title),
                                    }"
                                />
                                <Icon
                                    name="minus"
                                    class="absolute right-0 h-4 w-4 transition-all duration-200 ease-in-out"
                                    :class="{
                                        '!scale-0 !rotate-90': !isCategoryOpen(
                                            category.title,
                                        ),
                                        '!scale-100 !rotate-0': isCategoryOpen(
                                            category.title,
                                        ),
                                    }"
                                />
                            </div>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="mt-2 ml-2 space-y-2">
                                <div
                                    v-for="skill in category.skills"
                                    :key="skill.id"
                                    class="text-menu flex items-center justify-between"
                                >
                                    <span>{{ skill.name }}</span>
                                    <span class="text-menu/60 text-xs">
                                        {{ skill.level }} ·
                                        {{ skill.experience }}
                                    </span>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </ScrollArea>
        </div>
    </div>
</template>
