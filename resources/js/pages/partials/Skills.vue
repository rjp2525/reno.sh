<script lang="ts" setup>
import { MonoSkillCollapsible } from '@/components/page';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useCodeHighlighter } from '@/composables';
import type { SkillCategory } from '@/types/about';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    content: SkillCategory[];
}>();

const { highlightCode, isLoading } = useCodeHighlighter({
    theme: 'github-dark',
});
const highlightedCode = ref('');

const generateCodeBlock = (categories: SkillCategory[]) => {
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
        codeString += `${index === 0 ? '' : '    '}{
      title: "${category.title}",
      skills: [`;
        category.skills.forEach((skill, idx) => {
            codeString += `${idx === 0 ? '' : '      '}{
        name: "${skill.name}",
        proficiency: "${skill.level}",
        experience: "${skill.experience}"
      },\n`;
        });

        codeString += `    ]}${count === index + 1 ? '];' : ',\n'}`;
    });

    codeString += `
  }

  displaySkills(): void {
    this.categories.forEach((category) => {
      console.log(\`\\n\${category.title}\`);
      category.skills.forEach((skill) => {
        console.log(\`- \${skill.name} (\${skill.experience})\`);
      });
    });
  }
}

const skills = new Skills();
skills.displaySkills();
`;

    return codeString;
};

const code = computed(() => generateCodeBlock(props.content));

onMounted(async () => {
    highlightedCode.value = await highlightCode(code.value, 'typescript');
});

watch(code, async (newCode) => {
    highlightedCode.value = await highlightCode(newCode, 'typescript');
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
            <h3 class="text-menu px-8 pt-8">// Technical Skills</h3>
            <ScrollArea class="h-[calc(100vh-220px)] w-full p-8 pt-0">
                <div class="flex flex-col space-y-3">
                    <MonoSkillCollapsible
                        v-for="(category, key) in content"
                        :key="category.title"
                        :category="category"
                        :default-open="key === 0"
                    />
                </div>
            </ScrollArea>
        </div>
    </div>
</template>
