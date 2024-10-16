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
    name: string;
    skills: Skill[];
}
