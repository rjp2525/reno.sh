interface PhotoCategory {
    id: string;
    name: string;
    slug: string;
    color: string | null;
    photo_count?: number;
}

interface PhotoTag {
    id: number;
    name: string;
    slug: string;
}

interface PhotoExif {
    camera: string | null;
    lens: string | null;
    focal_length: string | null;
    aperture: string | null;
    shutter_speed: string | null;
    iso: number | null;
    summary: string | null;
}

interface TiptapContent {
    type: string;
    content?: TiptapContent[];
    text?: string;
    attrs?: Record<string, unknown>;
    marks?: Array<{ type: string; attrs?: Record<string, unknown> }>;
}

interface Photo {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    thumbnail_url: string | null;
    web_url: string | null;
    og_image_url: string | null;
    width: number | null;
    height: number | null;
    aspect_ratio: number;
    category: PhotoCategory | null;
    tags: PhotoTag[];
    exif: PhotoExif;
    taken_at: string | null;
    is_favorite: boolean;
    instagram_link: string | null;
    gps_latitude: number | null;
    gps_longitude: number | null;
}

interface PhotoFilters {
    category?: string;
    tags?: string | string[];
    favorites?: boolean;
}

interface PhotoFilterOptions {
    categories: PhotoCategory[];
    tags: PhotoTag[];
    totalPhotos: number;
}

interface PaginatedPhotos {
    data: Photo[];
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
