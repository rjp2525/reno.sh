<script lang="ts" setup>
import { MonoSkillCollapsible } from '@/components/page';
import { ScrollArea } from '@/components/ui/scroll-area';
import VCodeBlock from '@wdns/vue-code-block';
import { ref } from 'vue';

const categories = [
    {
        title: 'Backend',
        skills: [
            {
                name: 'TypeScript',
                proficiency: 'Expert',
                experience: '6 years',
            },
            {
                name: 'Node.js',
                proficiency: 'Advanced',
                experience: '7 years',
            },
            {
                name: 'Express',
                proficiency: 'Advanced',
                experience: '6 years',
            },
            {
                name: 'MySQL',
                proficiency: 'Intermediate',
                experience: '5 years',
            },
        ],
    },
    {
        title: 'Frontend',
        skills: [
            {
                name: 'JavaScript',
                proficiency: 'Expert',
                experience: '8 years',
            },
            {
                name: 'Vue.js',
                proficiency: 'Advanced',
                experience: '6 years',
            },
            {
                name: 'React',
                proficiency: 'Intermediate',
                experience: '3 years',
            },
            {
                name: 'Tailwind CSS',
                proficiency: 'Advanced',
                experience: '5 years',
            },
        ],
    },
    {
        title: 'Tools & DevOps',
        skills: [
            {
                name: 'Git',
                proficiency: 'Expert',
                experience: '10 years',
            },
            {
                name: 'Docker',
                proficiency: 'Intermediate',
                experience: '4 years',
            },
            {
                name: 'CI/CD',
                proficiency: 'Intermediate',
                experience: '4 years',
            },
            {
                name: 'Kubernetes',
                proficiency: 'Beginner',
                experience: '2 years',
            },
        ],
    },
    {
        title: 'Other',
        skills: [
            {
                name: 'Agile Methodology',
                proficiency: 'Advanced',
                experience: '6 years',
            },
            {
                name: 'Leadership',
                proficiency: 'Advanced',
                experience: '4 years',
            },
            {
                name: 'Project Management',
                proficiency: 'Intermediate',
                experience: '3 years',
            },
        ],
    },
];

const code = ref(`
interface Skill {
  name: string;
  proficiency: string;
  experience: string;
}

interface Category {
  title: string;
  skills: Skill[];
}

class Skills {
  public categories: Category[];

  constructor() {
    this.categories = [
    {
      title: "Backend",
        skills: [{
          name: "TypeScript",
          proficiency: "Expert",
          experience: "6 years"
        }, {
          name: "Node.js",
          proficiency: "Advanced",
          experience: "7 years"
        }, {
          name: "Express",
          proficiency: "Advanced",
          experience: "6 years"
        }, {
          name: "MySQL",
          proficiency: "Intermediate",
          experience: "5 years"
        }]
      },
      {
        title: "Frontend",
        skills: [{
          name: "JavaScript",
          proficiency: "Expert",
          experience: "8 years"
        }, {
          name: "Vue.js",
          proficiency: "Advanced",
          experience: "6 years"
        }, {
          name: "React",
          proficiency: "Intermediate",
          experience: "3 years"
        }, {
          name: "Tailwind CSS",
          proficiency: "Advanced",
          experience: "5 years"
        }]
      },
      {
        title: "Tools & DevOps",
        skills: [{
          name: "Git",
          proficiency: "Expert",
          experience: "10 years"
        }, {
          name: "Docker",
          proficiency: "Intermediate",
          experience: "4 years"
        }, {
          name: "CI/CD",
          proficiency: "Intermediate",
          experience: "4 years"
        }, {
          name: "Kubernetes",
          proficiency: "Beginner",
          experience: "2 years"
        }]
      },
      {
        title: "Other",
        skills: [{
          name: "Agile Methodology",
          proficiency: "Advanced",
          experience: "6 years"
        }, {
          name: "Leadership",
          proficiency: "Advanced",
          experience: "4 years"
        }, {
          name: "Project Management",
          proficiency: "Intermediate",
          experience: "3 years"
        }]
      }
    ];
  }

  displaySkills(): void {
    this.categories.forEach((category) => {
      console.log(\`\\n\${category.title}\`);
      category.skills.forEach((skill) => {
        console.log(\`- \${skill.name} (\${skill.proficiency}, \${skill.experience})\`);
      });
    });
  }
}

const skills = new Skills();
skills.displaySkills();
`);
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
                        v-for="(category, key) in categories"
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
