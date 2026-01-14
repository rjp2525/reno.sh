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

export interface ContentPage {
    slug: string;
    title: string;
    icon: string | null;
    content: TiptapContent | string | null;
}

export interface TiptapContent {
    type: string;
    content?: TiptapContent[];
    text?: string;
    attrs?: Record<string, unknown>;
    marks?: Array<{ type: string; attrs?: Record<string, unknown> }>;
}

// Props interfaces for pages
export interface ProfessionalPageProps {
    activeTab: string;
    tabs: Record<string, string>;
    experience: ExperienceItem[];
    skills: SkillCategory[];
    education: EducationItem[];
}

export interface PersonalPageProps {
    activeTab: string;
    tabs: Record<string, string>;
    content: TiptapContent | string | null;
    pages: ContentPage[];
}

export interface HobbiesPageProps {
    activeTab: string;
    tabs: Record<string, string>;
    content: TiptapContent | string | null;
    pages: ContentPage[];
}

// Legacy types for backward compatibility with PageMenu component
import type { Component } from 'vue';

export interface SectionInfo {
    name: string;
    component: Component;
    content:
        | ExperienceItem[]
        | SkillCategory[]
        | TiptapContent
        | string
        | null
        | undefined;
    type?: string;
}

export interface SectionType {
    name: string;
    icon: string;
    info: SectionInfo[];
}
