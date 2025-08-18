export interface ExperienceItem {
    id: number;
    title: string;
    company: string;
    location: string | null;
    start: string;
    end: string;
    responsibilities: string[];
    tenure: string;
}

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

interface PageBuilderContent {
    type: string;
    data: {
        column_span?: number | string;
        content: string;
    };
}

interface PageContent {
    id?: number;
    title: string;
    slug: string;
    content: PageBuilderContent[] | ExperienceItem[] | SkillCategory[] | null;
    type?: string;
    created_at?: string;
    updated_at?: string;
}

interface Section {
    name: string;
    icon: string;
    pages: PageContent[];
}

interface SectionContentType {
    id: number;
    title: string;
    slug: string;
    section: string;
    content: PageBuilderContent[] | null;
    created_at: string;
    updated_at: string;
}

interface SectionInfo {
    name: string;
    component: Component;
    content:
        | SectionContentType[]
        | ExperienceItem[]
        | SkillCategory[]
        | PageBuilderContent[]
        | null
        | undefined;
    type?: string;
}

interface SectionType {
    name: string;
    icon: string;
    info: SectionInfo[];
}
