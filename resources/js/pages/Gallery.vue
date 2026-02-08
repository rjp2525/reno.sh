<script setup lang="ts">
import { GallerySidebar, SeoHead } from '@/components';
import { MobilePageHeader } from '@/components/page';
import { SearchInput, SelectInput } from '@/components/ui/form';
import { GuestLayout } from '@/layouts';
import { gallery as galleryRoute } from '@/routes';
import { Link, router, WhenVisible } from '@inertiajs/vue3';
import { Camera, Loader2, Star } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type SortOption = 'newest' | 'oldest' | 'title_asc' | 'title_desc';

const props = defineProps<{
    photos: PaginatedPhotos;
    filters: PhotoFilters;
    filterOptions: PhotoFilterOptions;
}>();

const baseGalleryUrl = galleryRoute.url();

const allPhotos = ref<Photo[]>([...props.photos.data]);
const currentPage = ref(props.photos.current_page);
const hasMorePages = computed(() => currentPage.value < props.photos.last_page);
const isLoadingMore = ref(false);

const selectedCategory = ref(props.filters.category || '');
const selectedTags = ref<string[]>(
    Array.isArray(props.filters.tags)
        ? props.filters.tags
        : props.filters.tags
          ? [props.filters.tags]
          : [],
);

const searchQuery = ref('');
const sortBy = ref<SortOption>('newest');

const stripHtml = (html: string | null): string => {
    if (!html) return '';
    return html
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
};

const displayedPhotos = computed(() => {
    let photos = [...allPhotos.value];

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        photos = photos.filter((photo) => {
            const descriptionText = stripHtml(photo.description);
            return (
                photo.title.toLowerCase().includes(query) ||
                descriptionText.toLowerCase().includes(query) ||
                photo.tags?.some((tag) =>
                    tag.name.toLowerCase().includes(query),
                )
            );
        });
    }

    switch (sortBy.value) {
        case 'oldest':
            photos.sort(
                (a, b) =>
                    new Date(a.taken_at || 0).getTime() -
                    new Date(b.taken_at || 0).getTime(),
            );
            break;
        case 'title_asc':
            photos.sort((a, b) => a.title.localeCompare(b.title));
            break;
        case 'title_desc':
            photos.sort((a, b) => b.title.localeCompare(a.title));
            break;
        case 'newest':
        default:
            break;
    }

    return photos;
});

watch(
    () => props.photos,
    (newPhotos) => {
        if (newPhotos.current_page === 1) {
            allPhotos.value = [...newPhotos.data];
            currentPage.value = 1;
        }
    },
);

const loadMore = () => {
    if (isLoadingMore.value || !hasMorePages.value) return;

    isLoadingMore.value = true;
    const nextPage = currentPage.value + 1;

    const params: { page: number; category?: string; tags?: string[] } = {
        page: nextPage,
    };
    if (selectedCategory.value) params.category = selectedCategory.value;
    if (selectedTags.value.length) params.tags = selectedTags.value;

    router.get(baseGalleryUrl, params, {
        preserveState: true,
        preserveScroll: true,
        only: ['photos'],
        onSuccess: (page) => {
            const newPhotos = (
                page.props as unknown as { photos: PaginatedPhotos }
            ).photos;
            allPhotos.value = [...allPhotos.value, ...newPhotos.data];
            currentPage.value = newPhotos.current_page;
            isLoadingMore.value = false;
        },
        onError: () => {
            isLoadingMore.value = false;
        },
    });
};

const selectCategory = (slug: string) => {
    selectedCategory.value = slug;
    applyFilters();
};

const toggleTag = (slug: string) => {
    const index = selectedTags.value.indexOf(slug);
    if (index > -1) {
        selectedTags.value.splice(index, 1);
    } else {
        selectedTags.value.push(slug);
    }
    applyFilters();
};

const applyFilters = () => {
    const params: { category?: string; tags?: string[] } = {};

    if (selectedCategory.value) params.category = selectedCategory.value;
    if (selectedTags.value.length) params.tags = selectedTags.value;

    router.get(baseGalleryUrl, params, {
        preserveState: false,
        preserveScroll: false,
    });
};

const getPhotoUrl = (photo: Photo) => `${baseGalleryUrl}/${photo.slug}`;

const hasActiveFilters = computed(() => {
    return (
        selectedCategory.value ||
        selectedTags.value.length > 0 ||
        searchQuery.value.trim()
    );
});
</script>

<template>
    <GuestLayout>
        <SeoHead
            title="Photography"
            description="A collection of the best moments captured through my lens, mainly shot on a Sony A7IV."
        />

        <main class="flex h-full w-full flex-auto flex-col overflow-hidden lg:flex-row">
            <MobilePageHeader>_photography</MobilePageHeader>

            <GallerySidebar
                :categories="filterOptions.categories"
                :tags="filterOptions.tags"
                :total-photos="filterOptions.totalPhotos"
                :selected-category="selectedCategory"
                :selected-tags="selectedTags"
                @select-category="selectCategory"
                @toggle-tag="toggleTag"
            />

            <div class="flex h-full w-full flex-col overflow-hidden">
                <div
                    class="flex items-center gap-2 border-b border-[#1E2D3D] px-4 py-2 lg:hidden"
                >
                    <SearchInput
                        v-model="searchQuery"
                        placeholder="Search photos..."
                        class="min-w-0 flex-1"
                    />
                    <select
                        v-model="selectedCategory"
                        class="shrink-0 rounded-md border border-[#1E2D3D] bg-[#011221] px-2 py-1.5 text-xs text-white"
                        @change="applyFilters"
                    >
                        <option value="">All</option>
                        <option
                            v-for="category in filterOptions.categories"
                            :key="category.id"
                            :value="category.slug"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <SelectInput v-model="sortBy" class="shrink-0 text-xs">
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="title_asc">A-Z</option>
                        <option value="title_desc">Z-A</option>
                    </SelectInput>
                </div>

                <div
                    class="h-full overflow-y-auto p-4 sm:p-6"
                    @contextmenu.prevent
                >
                    <div class="mb-6 hidden lg:block">
                        <h1 class="mb-2 text-2xl font-semibold text-white">
                            _photography
                        </h1>
                        <p class="text-sm text-[#607B96] lowercase">
                            // a collection of the best moments captured through
                            my lens, mainly shot on a Sony A7IV
                        </p>
                    </div>

                    <div
                        class="mb-6 hidden gap-3 sm:flex-row sm:items-center sm:justify-between lg:flex"
                    >
                        <SearchInput
                            v-model="searchQuery"
                            placeholder="Search photos..."
                            class="flex-1 sm:max-w-md"
                        />

                        <SelectInput v-model="sortBy">
                            <option value="newest">Sort by: Newest</option>
                            <option value="oldest">Sort by: Oldest</option>
                            <option value="title_asc">Sort by: A-Z</option>
                            <option value="title_desc">Sort by: Z-A</option>
                        </SelectInput>
                    </div>

                    <div v-if="displayedPhotos.length > 0" class="masonry-grid">
                        <Link
                            v-for="photo in displayedPhotos"
                            :key="photo.id"
                            :href="getPhotoUrl(photo)"
                            class="masonry-item group"
                            @dragstart.prevent
                        >
                            <div
                                class="relative overflow-hidden rounded-lg bg-[#011221]"
                            >
                                <div
                                    v-if="photo.thumbnail_url"
                                    class="protected-thumbnail w-full transition-transform duration-300 group-hover:scale-105"
                                    :style="{
                                        backgroundImage: `url(${photo.thumbnail_url})`,
                                        paddingBottom: `${(1 / photo.aspect_ratio) * 100}%`,
                                    }"
                                    role="img"
                                    :aria-label="photo.title"
                                />
                                <div
                                    v-else
                                    class="flex aspect-[4/3] items-center justify-center bg-[#0a1628]"
                                >
                                    <Camera class="h-8 w-8 text-[#607B96]" />
                                </div>

                                <div
                                    class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/80 via-black/20 to-transparent p-3 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                >
                                    <h3
                                        class="line-clamp-1 text-sm font-medium text-white"
                                    >
                                        {{ photo.title }}
                                    </h3>
                                    <p
                                        v-if="photo.exif.summary"
                                        class="mt-1 line-clamp-1 text-xs text-gray-300"
                                    >
                                        {{ photo.exif.summary }}
                                    </p>
                                </div>

                                <div
                                    v-if="photo.is_favorite"
                                    class="absolute top-2 right-2 rounded-full bg-black/50 p-1"
                                >
                                    <Star
                                        class="h-3 w-3 fill-yellow-400 text-yellow-400"
                                    />
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div
                        v-else
                        class="flex h-full flex-col items-center justify-center py-12"
                    >
                        <Camera class="h-12 w-12 text-[#607B96]" />
                        <h3 class="mt-4 text-sm font-medium text-white">
                            {{
                                searchQuery.trim()
                                    ? 'No photos found'
                                    : 'No photos yet'
                            }}
                        </h3>
                        <p class="mt-1 text-sm text-[#607B96]">
                            {{
                                searchQuery.trim()
                                    ? 'Try a different search term.'
                                    : hasActiveFilters
                                      ? 'Try adjusting your filters.'
                                      : 'Check back soon for new photos!'
                            }}
                        </p>
                    </div>

                    <WhenVisible
                        v-if="hasMorePages && allPhotos.length > 0"
                        :once="false"
                        @appear="loadMore"
                    >
                        <div class="flex justify-center py-8">
                            <Loader2
                                v-if="isLoadingMore"
                                class="h-6 w-6 animate-spin text-[#607B96]"
                            />
                            <span v-else class="text-sm text-[#607B96]">
                                Loading more...
                            </span>
                        </div>
                    </WhenVisible>
                </div>
            </div>
        </main>
    </GuestLayout>
</template>

<style scoped>
.masonry-grid {
    columns: 2;
    column-gap: 1rem;
}

@media (min-width: 640px) {
    .masonry-grid {
        columns: 3;
    }
}

@media (min-width: 1024px) {
    .masonry-grid {
        columns: 3;
    }
}

@media (min-width: 1280px) {
    .masonry-grid {
        columns: 4;
    }
}

@media (min-width: 1536px) {
    .masonry-grid {
        columns: 5;
    }
}

.masonry-item {
    display: block;
    break-inside: avoid;
    margin-bottom: 1rem;
}

.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.protected-thumbnail {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    user-select: none;
    -webkit-user-select: none;
    -webkit-touch-callout: none;
}
</style>
