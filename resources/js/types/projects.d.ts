interface Project {
    id: string;
    title: string;
    slug: string;
    summary: string;
    description: string;
    type: 'personal' | 'professional' | 'open_source' | 'other' | 'case_study';
    status: 'active' | 'archived' | 'in_development';

    featured_image: string | null;
    gallery: Array<{ image: string }> | null;
    url: string | null;
    github_url: string | null;
    demo_url: string | null;

    start_date: string | null;
    end_date: string | null;
    is_ongoing: boolean;

    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string[] | null;
    og_image: string | null;

    is_featured: boolean;
    sort_order: number;
    is_published: boolean;

    tags: Tag[];
    technologies: Technology[];

    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

interface Technology {
    id: number;
    name: string;
    slug: string;
    category: string | null;
    color: string | null;
    icon: string | null;
    description: string | null;
    website_url: string | null;
    sort_order: number;
    is_active: boolean;
    pivot?: {
        proficiency_level: string | null;
        is_primary: boolean;
    };
    created_at: string;
    updated_at: string;
}

interface ProjectFilters {
    search?: string;
    type?: string;
    status?: string;
    tags?: string | string[];
    technologies?: string | string[];
    featured?: boolean;
}

interface ProjectFilterOptions {
    tags: Array<{
        id: number;
        name: string;
        slug: string;
    }>;
    technologies: Array<{
        id: number;
        name: string;
        slug: string;
        category: string | null;
        color: string | null;
    }>;
    types: Array<{
        value: string;
        label: string;
    }>;
}

interface PaginatedProjects {
    data: Project[];
    current_page: number;
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
}
