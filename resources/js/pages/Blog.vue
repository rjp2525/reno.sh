<script setup lang="ts">
import { BlogSidebar, SeoHead } from '@/components';
import { MobilePageHeader } from '@/components/page';
import { SearchInput } from '@/components/ui/form';
import { GuestLayout } from '@/layouts';
import {
    category as blogCategory,
    index as blogIndex,
    show as blogShow,
} from '@/routes/blog';
import { Link, router } from '@inertiajs/vue3';
import { BookOpen, Clock, Star } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    posts: PaginatedBlogPosts;
    currentCategory: BlogCategory | null;
    filters: BlogFilters;
    filterOptions: BlogFilterOptions;
}>();

const searchQuery = ref(props.filters.search || '');
const selectedTags = ref<string[]>(
    Array.isArray(props.filters.tags)
        ? props.filters.tags
        : props.filters.tags
          ? [props.filters.tags]
          : [],
);

const totalPosts = computed(() => props.posts.total);

let searchTimeout: number;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

const selectCategory = (slug: string) => {
    if (slug) {
        router.get(blogCategory.url({ category: slug }), buildParams(), {
            preserveState: false,
        });
    } else {
        router.get(blogIndex.url(), buildParams(), {
            preserveState: false,
        });
    }
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

const buildParams = () => {
    const params: Record<string, any> = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedTags.value.length) params.tags = selectedTags.value;
    return params;
};

const applyFilters = () => {
    const baseUrl = props.currentCategory
        ? blogCategory.url({ category: props.currentCategory.slug })
        : blogIndex.url();

    router.get(baseUrl, buildParams(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const hasActiveFilters = computed(() => {
    return searchQuery.value || selectedTags.value.length > 0;
});

const clearFilters = () => {
    searchQuery.value = '';
    selectedTags.value = [];
    const baseUrl = props.currentCategory
        ? blogCategory.url({ category: props.currentCategory.slug })
        : blogIndex.url();
    router.get(baseUrl);
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getPostUrl = (post: BlogPostSummary) => {
    if (post.category) {
        return blogShow.url({
            category: post.category.slug,
            post: post.slug,
        });
    }
    return `/blog/post/${post.slug}`;
};

const pageTitle = computed(() =>
    props.currentCategory ? `Blog - ${props.currentCategory.name}` : 'Blog',
);

const pageDescription = computed(
    () =>
        props.currentCategory?.description ||
        'Thoughts, tutorials, insights on web development, or just rambling about whatever I feel like.',
);
</script>

<template>
    <GuestLayout>
        <SeoHead :title="pageTitle" :description="pageDescription" />

        <main class="flex h-full w-full flex-auto flex-col overflow-hidden lg:flex-row">
            <MobilePageHeader>_blog</MobilePageHeader>

            <BlogSidebar
                :categories="filterOptions.categories"
                :tags="filterOptions.tags"
                :selected-category="currentCategory?.slug || ''"
                :selected-tags="selectedTags"
                :total-posts="totalPosts"
                @select-category="selectCategory"
                @toggle-tag="toggleTag"
            />

            <div class="flex h-full w-full flex-col overflow-hidden">
                <div
                    class="flex items-center gap-2 border-b border-[#1E2D3D] px-4 py-2 lg:hidden"
                >
                    <SearchInput
                        v-model="searchQuery"
                        placeholder="Search posts..."
                        class="min-w-0 flex-1"
                    />
                    <select
                        class="shrink-0 rounded-md border border-[#1E2D3D] bg-[#011221] px-2 py-1.5 text-xs text-white"
                        @change="
                            selectCategory(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">All</option>
                        <option
                            v-for="cat in filterOptions.categories"
                            :key="cat.id"
                            :value="cat.slug"
                            :selected="currentCategory?.slug === cat.slug"
                        >
                            {{ cat.name }}
                        </option>
                    </select>
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="shrink-0 text-xs text-[#607B96] underline hover:text-white"
                    >
                        Clear
                    </button>
                </div>

                <div class="h-full overflow-y-auto">
                    <div class="p-4 pb-0 lg:p-6 lg:pb-0">
                        <div class="mb-6 hidden lg:block">
                            <h1 class="mb-2 text-2xl font-semibold text-white">
                                {{
                                    currentCategory
                                        ? currentCategory.name
                                        : '_blog'
                                }}
                            </h1>
                            <p class="text-sm text-[#607B96] lowercase">
                                // {{ pageDescription }}
                            </p>
                        </div>

                        <div
                            class="mb-6 hidden gap-3 sm:flex-row sm:items-center sm:justify-between lg:flex"
                        >
                            <SearchInput
                                v-model="searchQuery"
                                placeholder="Search posts..."
                                class="flex-1 sm:max-w-md"
                            />

                            <button
                                v-if="hasActiveFilters"
                                @click="clearFilters"
                                class="shrink-0 text-sm text-[#607B96] underline hover:text-white"
                            >
                                Clear filters
                            </button>
                        </div>
                    </div>

                    <div v-if="posts.data.length > 0" class="p-4 pt-0 lg:p-6 lg:pt-0">
                        <div class="mb-4 text-sm text-[#607B96]">
                            Showing {{ posts.from }}-{{ posts.to }} of
                            {{ posts.total }} posts
                        </div>

                        <!-- Mobile: compact horizontal list -->
                        <div class="space-y-3 lg:hidden">
                            <Link
                                v-for="post in posts.data"
                                :key="post.id"
                                :href="getPostUrl(post)"
                                class="group flex gap-3 overflow-hidden rounded-lg border border-[#1E2D3D] bg-[#011221] transition-all duration-200 hover:border-[#607B96]/50"
                            >
                                <div
                                    class="relative h-24 w-24 shrink-0 overflow-hidden bg-[#0a1628]"
                                >
                                    <img
                                        v-if="post.featured_image"
                                        :src="`/img/${post.featured_image}`"
                                        :alt="post.title"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="no-image-card flex h-full items-center justify-center"
                                    >
                                        <BookOpen class="h-5 w-5 text-[#1E2D3D]" />
                                    </div>
                                    <div
                                        v-if="post.is_featured"
                                        class="absolute top-1 right-1"
                                    >
                                        <Star class="h-3 w-3 fill-yellow-400 text-yellow-400" />
                                    </div>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col justify-center gap-1 py-2 pr-3">
                                    <h3 class="line-clamp-2 text-sm font-semibold text-white group-hover:text-[#FEA55F]">
                                        {{ post.title }}
                                    </h3>
                                    <div class="flex items-center gap-2 text-xs text-[#607B96]">
                                        <span class="flex items-center gap-1">
                                            <Clock class="h-3 w-3" />
                                            {{ post.reading_time }} min
                                        </span>
                                        <span v-if="post.published_at">
                                            {{ formatDate(post.published_at) }}
                                        </span>
                                    </div>
                                    <span
                                        v-if="post.category"
                                        class="w-fit rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                        :style="{
                                            backgroundColor: (post.category.color || '#607B96') + '30',
                                            color: post.category.color || '#607B96',
                                        }"
                                    >
                                        {{ post.category.name }}
                                    </span>
                                </div>
                            </Link>
                        </div>

                        <!-- Desktop: card grid -->
                        <div class="hidden gap-6 md:grid-cols-2 lg:grid lg:grid-cols-3">
                            <Link
                                v-for="post in posts.data"
                                :key="post.id"
                                :href="getPostUrl(post)"
                                class="group overflow-hidden rounded-xl border border-[#1E2D3D] bg-[#011221] transition-all duration-200 hover:border-[#607B96]/50"
                            >
                                <div
                                    class="relative aspect-video overflow-hidden bg-[#0a1628]"
                                >
                                    <img
                                        v-if="post.featured_image"
                                        :src="`/img/${post.featured_image}`"
                                        :alt="post.title"
                                        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                    />
                                    <div
                                        v-else
                                        class="no-image-card flex h-full flex-col items-center justify-center gap-3 p-6"
                                    >
                                        <BookOpen
                                            class="h-8 w-8 text-[#1E2D3D]"
                                        />
                                        <span
                                            class="line-clamp-2 text-center text-sm font-medium text-[#607B96]/60"
                                        >
                                            {{ post.title }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="post.is_featured"
                                        class="absolute top-3 right-3"
                                    >
                                        <Star
                                            class="h-4 w-4 fill-yellow-400 text-yellow-400"
                                        />
                                    </div>

                                    <div
                                        v-if="post.category"
                                        class="absolute bottom-3 left-3"
                                    >
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-medium"
                                            :style="{
                                                backgroundColor:
                                                    (post.category.color ||
                                                        '#607B96') + '30',
                                                color:
                                                    post.category.color ||
                                                    '#607B96',
                                            }"
                                        >
                                            {{ post.category.name }}
                                        </span>
                                    </div>
                                </div>

                                <div class="space-y-3 p-4">
                                    <h3
                                        class="line-clamp-2 font-semibold text-white transition-colors group-hover:text-[#FEA55F]"
                                    >
                                        {{ post.title }}
                                    </h3>

                                    <p
                                        v-if="post.excerpt"
                                        class="line-clamp-2 text-sm text-[#607B96]"
                                    >
                                        {{ post.excerpt }}
                                    </p>

                                    <div
                                        class="flex items-center gap-3 text-xs text-[#607B96]"
                                    >
                                        <span class="flex items-center gap-1">
                                            <Clock class="h-3 w-3" />
                                            {{ post.reading_time }} min read
                                        </span>
                                        <span v-if="post.published_at">
                                            {{ formatDate(post.published_at) }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="post.tags.length"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="tag in post.tags.slice(0, 3)"
                                            :key="tag.id"
                                            class="rounded-full border border-[#1E2D3D] bg-[#011221] px-2 py-0.5 text-xs text-[#607B96]"
                                        >
                                            #{{ tag.name }}
                                        </span>
                                        <span
                                            v-if="post.tags.length > 3"
                                            class="px-1 text-xs text-[#607B96]"
                                        >
                                            +{{ post.tags.length - 3 }}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        </div>

                        <div
                            v-if="posts.last_page > 1"
                            class="flex items-center justify-center space-x-2 pt-8"
                        >
                            <Link
                                v-if="posts.prev_page_url"
                                :href="posts.prev_page_url"
                                class="rounded-lg border border-[#1E2D3D] bg-[#011221] px-3 py-2 text-sm text-[#607B96] hover:text-white"
                            >
                                Previous
                            </Link>

                            <template
                                v-for="link in posts.links"
                                :key="link.label"
                            >
                                <Link
                                    v-if="
                                        link.url &&
                                        !['Previous', 'Next'].includes(
                                            link.label,
                                        )
                                    "
                                    :href="link.url"
                                    :class="[
                                        'rounded-lg border px-3 py-2 text-sm',
                                        link.active
                                            ? 'border-[#FEA55F] bg-[#FEA55F]/20 text-[#FEA55F]'
                                            : 'border-[#1E2D3D] bg-[#011221] text-[#607B96] hover:text-white',
                                    ]"
                                >
                                    {{ link.label }}
                                </Link>
                                <span
                                    v-else-if="
                                        !link.url &&
                                        !['Previous', 'Next'].includes(
                                            link.label,
                                        )
                                    "
                                    class="px-3 py-2 text-sm text-[#607B96]/50"
                                >
                                    {{ link.label }}
                                </span>
                            </template>

                            <Link
                                v-if="posts.next_page_url"
                                :href="posts.next_page_url"
                                class="rounded-lg border border-[#1E2D3D] bg-[#011221] px-3 py-2 text-sm text-[#607B96] hover:text-white"
                            >
                                Next
                            </Link>
                        </div>
                    </div>

                    <div v-else class="py-12 text-center">
                        <BookOpen class="mx-auto h-12 w-12 text-[#607B96]" />
                        <h3 class="mt-4 text-sm font-medium text-white">
                            {{
                                hasActiveFilters
                                    ? 'No posts found'
                                    : 'No posts yet'
                            }}
                        </h3>
                        <p class="mt-1 text-sm text-[#607B96]">
                            {{
                                hasActiveFilters
                                    ? 'Try adjusting your filters or search terms.'
                                    : 'Check back soon for new posts!'
                            }}
                        </p>
                        <button
                            v-if="hasActiveFilters"
                            @click="clearFilters"
                            class="mt-4 rounded-lg border border-[#1E2D3D] bg-[#011221] px-4 py-2 text-sm text-[#607B96] hover:text-white"
                        >
                            Clear all filters
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </GuestLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.no-image-card {
    background: linear-gradient(135deg, #011221 0%, #0a1628 50%, #011627 100%);
    background-size: 100% 100%;
}
</style>
