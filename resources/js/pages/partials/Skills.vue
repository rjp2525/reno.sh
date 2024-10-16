<script lang="ts" setup>
import { MonoSkillCollapsible } from '@/components/page';
import { ScrollArea } from '@/components/ui/scroll-area';
import { SkillCategory } from '@/types/about';
import VCodeBlock from '@wdns/vue-code-block';
import { computed } from 'vue';

const props = defineProps<{
    content: SkillCategory[];
}>();

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
        category.skills.forEach((skill, index) => {
            codeString += `${index === 0 ? '' : '      '}{
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
</script>

<template>
    <div class="relative flex h-full w-full flex-col lg:grid lg:grid-cols-2">
        <div>
            <ScrollArea class="h-[calc(100vh-150px)] w-full p-6">
                <VCodeBlock
                    :code="code"
                    highlightjs
                    lang="ts"
                    :indent="2"
                    theme="night-owl"
                    :copy-button="false"
                />
            </ScrollArea>
        </div>
        <div class="space-y-4 border-l border-l-navy text-sm">
            <h3 class="px-8 pt-8">// Technical Skills</h3>
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

<style lang="scss">
code.language-ts.hljs {
    background: transparent !important;
}
</style>
