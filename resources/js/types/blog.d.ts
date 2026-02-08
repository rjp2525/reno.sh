interface BlogCategory {
    id: string;
    name: string;
    slug: string;
    color: string | null;
    description?: string | null;
    post_count?: number;
}

interface BlogTag {
    id: number;
    name: string;
    slug: string;
}

interface BlogSeriesSummary {
    title: string;
    slug: string;
}

interface BlogPostSummary {
    id: string;
    title: string;
    slug: string;
    excerpt: string | null;
    featured_image: string | null;
    reading_time: number;
    published_at: string | null;
    is_featured: boolean;
    category: BlogCategory | null;
    tags: BlogTag[];
    series: BlogSeriesSummary | null;
}

interface BlogPost extends BlogPostSummary {
    content: import('./blocks').ContentBlock[] | null;
    updated_at: string | null;
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string[] | null;
    og_image: string | null;
}

interface BlogSeriesPost {
    id: string;
    title: string;
    slug: string;
    series_order: number;
    is_current: boolean;
    category_slug: string | null;
}

interface BlogAdjacentPost {
    title: string;
    slug: string;
    category_slug: string | null;
}

interface BlogSeriesDetail {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    featured_image: string | null;
    posts: Array<{
        id: string;
        title: string;
        slug: string;
        excerpt: string | null;
        reading_time: number;
        published_at: string | null;
        series_order: number;
        category: BlogCategory | null;
    }>;
}

interface TocEntry {
    title: string;
    level: 'h2' | 'h3' | 'h4';
    id: string;
}

interface BlogFilters {
    search?: string | null;
    tags?: string | string[] | null;
    featured?: boolean;
}

interface BlogFilterOptions {
    categories: BlogCategory[];
    tags: BlogTag[];
}

interface PaginatedBlogPosts {
    data: BlogPostSummary[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}
