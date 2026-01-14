<script setup lang="ts">
import { MobilePageHeader } from '@/components/page';
import { ScrollArea } from '@/components/ui/scroll-area';
import { GuestLayout } from '@/layouts';
import { projects as projectsRoute } from '@/routes';
import { show as projectShow } from '@/routes/projects';
import { Head, Link, router } from '@inertiajs/vue3';
import { AppWindowMac } from 'lucide-vue-next';
import { computed, inject, ref, watch } from 'vue';

const isMobile = inject('isMobile', ref(false));

const props = defineProps<{
    projects: PaginatedProjects;
    filters: ProjectFilters;
    filterOptions: ProjectFilterOptions;
}>();

const searchQuery = ref(props.filters.search || '');
const selectedType = ref(props.filters.type || '');
const selectedTags = ref<string[]>(
    Array.isArray(props.filters.tags)
        ? props.filters.tags
        : props.filters.tags
          ? [props.filters.tags]
          : [],
);
const selectedTechnologies = ref<string[]>(
    Array.isArray(props.filters.technologies)
        ? props.filters.technologies
        : props.filters.technologies
          ? [props.filters.technologies]
          : [],
);
const showFeaturedOnly = ref(props.filters.featured || false);

let searchTimeout: number;
watch(searchQuery, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

watch(
    [selectedType, selectedTags, selectedTechnologies, showFeaturedOnly],
    () => {
        applyFilters();
    },
    { deep: true },
);

const applyFilters = () => {
    const params: Record<string, any> = {};

    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedType.value) params.type = selectedType.value;
    if (selectedTags.value.length) params.tags = selectedTags.value;
    if (selectedTechnologies.value.length)
        params.technologies = selectedTechnologies.value;
    if (showFeaturedOnly.value) params.featured = true;

    router.get(projectsRoute.url(), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedType.value = '';
    selectedTags.value = [];
    selectedTechnologies.value = [];
    showFeaturedOnly.value = false;
    router.get(projectsRoute.url());
};

const toggleTag = (tagSlug: string) => {
    const index = selectedTags.value.indexOf(tagSlug);
    if (index > -1) {
        selectedTags.value.splice(index, 1);
    } else {
        selectedTags.value.push(tagSlug);
    }
};

const toggleTechnology = (techSlug: string) => {
    const index = selectedTechnologies.value.indexOf(techSlug);
    if (index > -1) {
        selectedTechnologies.value.splice(index, 1);
    } else {
        selectedTechnologies.value.push(techSlug);
    }
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
    });
};

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value ||
        selectedType.value ||
        selectedTags.value.length ||
        selectedTechnologies.value.length ||
        showFeaturedOnly.value
    );
});
</script>

<template>
    <GuestLayout>
        <Head title="Projects" />

        <main class="flex h-full w-full flex-auto flex-col overflow-hidden">
            <MobilePageHeader>_projects</MobilePageHeader>

            <div class="grid grid-cols-1 gap-4 gap-6 p-6 pb-0 md:grid-cols-2">
                <div class="mb-0">
                    <h1
                        class="mb-1 text-3xl font-bold text-gray-900 dark:text-white"
                    >
                        Projects
                    </h1>
                    <p class="text-gray-400">
                        Check out some of the cool things I've built for fun and
                        work.
                    </p>
                </div>

                <div class="flex items-center justify-end">
                    <div class="inline-flex items-center space-x-4">
                        <div class="relative w-full">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search projects..."
                                class="w-full rounded-md border border-gray-700 bg-gray-800 px-4 py-1 pl-10 transition-all duration-200 focus:border-orange-400 focus:ring-0 focus:outline-0"
                            />
                            <svg
                                class="absolute top-2 left-3 h-4 w-4 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                        </div>

                        <div class="flex w-full gap-4">
                            <select
                                v-model="selectedType"
                                class="rounded-md border border-gray-700 bg-gray-800 px-3 py-1.5 text-sm transition-all duration-200 focus:border-orange-400 focus:ring-0 focus:outline-0"
                            >
                                <option value="" class="text-sm">
                                    All Types
                                </option>
                                <option
                                    v-for="type in filterOptions.types"
                                    :key="type.value"
                                    :value="type.value"
                                    class="text-sm"
                                >
                                    {{ type.label }}
                                </option>
                            </select>

                            <label
                                class="flex cursor-pointer items-center space-x-2"
                            >
                                <input
                                    v-model="showFeaturedOnly"
                                    type="checkbox"
                                    class="rounded border-gray-700 bg-gray-600 text-gray-300 transition-all duration-200 checked:bg-orange-400 hover:bg-gray-500 checked:hover:bg-orange-500 focus:border-orange-400 focus:ring-0 focus:ring-offset-0 focus:outline-none focus:checked:bg-orange-400 active:ring-0 active:outline-none"
                                />
                                <span class="text-sm text-gray-300"
                                    >Featured</span
                                >
                            </label>

                            <button
                                v-if="hasActiveFilters"
                                @click="clearFilters"
                                class="px-3 py-2 text-sm text-gray-600 underline hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                Clear filters
                            </button>
                        </div>

                        <div
                            v-if="filterOptions.tags.length"
                            class="w-full space-y-2"
                        >
                            <h3
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Filter by Tags:
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="tag in filterOptions.tags"
                                    :key="tag.id"
                                    @click="toggleTag(tag.slug)"
                                    :class="[
                                        'rounded-full border px-3 py-1 text-xs transition-colors',
                                        selectedTags.includes(tag.slug)
                                            ? 'border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-600 dark:bg-blue-900 dark:text-blue-200'
                                            : 'border-gray-300 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                                    ]"
                                >
                                    {{ tag.name }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="filterOptions.technologies.length"
                            class="w-full space-y-2"
                        >
                            <h3
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Filter by Technologies:
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="tech in filterOptions.technologies"
                                    :key="tech.id"
                                    @click="toggleTechnology(tech.slug)"
                                    :class="[
                                        'rounded-full border px-3 py-1 text-xs transition-colors',
                                        selectedTechnologies.includes(tech.slug)
                                            ? 'border-green-300 bg-green-100 text-green-800 dark:border-green-600 dark:bg-green-900 dark:text-green-200'
                                            : 'border-gray-300 bg-gray-100 text-gray-700 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                                    ]"
                                    :style="
                                        tech.color &&
                                        selectedTechnologies.includes(tech.slug)
                                            ? {
                                                  backgroundColor:
                                                      tech.color + '20',
                                                  borderColor: tech.color,
                                                  color: tech.color,
                                              }
                                            : {}
                                    "
                                >
                                    {{ tech.name }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ScrollArea class="h-[calc(100vh-246px)] w-full p-6">
                <div v-if="projects.data.length > 0" class="space-y-8">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Showing {{ projects.from }}-{{ projects.to }} of
                        {{ projects.total }} projects
                    </div>

                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="project in projects.data"
                            :key="project.id"
                            class="group relative overflow-hidden rounded-xl border border-gray-700 bg-gray-800 shadow-sm transition-all duration-200 hover:shadow-lg"
                        >
                            <div
                                v-if="project.is_featured"
                                class="absolute top-4 right-4 z-10"
                            >
                                <span
                                    class="rounded-full bg-yellow-900 px-2 py-1 text-xs font-medium text-yellow-300"
                                >
                                    Featured
                                </span>
                            </div>

                            <div
                                class="relative aspect-video overflow-hidden bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-700 dark:to-gray-600"
                            >
                                <img
                                    v-if="project.featured_image"
                                    :src="`/storage/${project.featured_image}`"
                                    :alt="project.title"
                                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                />
                                <img
                                    v-else-if="
                                        project.gallery &&
                                        project.gallery.length > 0
                                    "
                                    :src="`/storage/${project.gallery[0].image}`"
                                    :alt="project.title"
                                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="flex h-full items-center justify-center"
                                >
                                    <svg
                                        class="h-12 w-12 text-gray-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <div class="space-y-4 p-6">
                                <div>
                                    <div
                                        class="mb-2 flex items-start justify-between"
                                    >
                                        <h3
                                            class="text-lg font-semibold text-gray-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400"
                                        >
                                            {{ project.title }}
                                        </h3>
                                        <span
                                            v-if="project.type"
                                            class="ml-2 shrink-0 rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                        >
                                            {{ project.type.replace('_', ' ') }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="
                                            project.start_date ||
                                            project.is_ongoing
                                        "
                                        class="mb-3 text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ formatDate(project.start_date) }}
                                        <span
                                            v-if="
                                                project.end_date &&
                                                !project.is_ongoing
                                            "
                                        >
                                            -
                                            {{
                                                formatDate(project.end_date)
                                            }}</span
                                        >
                                        <span v-else-if="project.is_ongoing">
                                            - Present</span
                                        >
                                    </div>

                                    <p
                                        class="line-clamp-3 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{
                                            project.summary ||
                                            project.description?.substring(
                                                0,
                                                120,
                                            ) + '...'
                                        }}
                                    </p>
                                </div>

                                <div
                                    v-if="project.technologies.length"
                                    class="space-y-2"
                                >
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="tech in project.technologies.slice(
                                                0,
                                                5,
                                            )"
                                            :key="tech.id"
                                            class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                            :style="
                                                tech.color
                                                    ? {
                                                          backgroundColor:
                                                              tech.color + '20',
                                                          color: tech.color,
                                                      }
                                                    : {}
                                            "
                                        >
                                            {{ tech.name }}
                                        </span>
                                        <span
                                            v-if="
                                                project.technologies.length > 5
                                            "
                                            class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-500"
                                        >
                                            +{{
                                                project.technologies.length - 5
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    v-if="project.tags.length"
                                    class="space-y-2"
                                >
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="tag in project.tags.slice(
                                                0,
                                                3,
                                            )"
                                            :key="tag.id"
                                            class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                        >
                                            {{ tag.name.en }}
                                        </span>
                                        <span
                                            v-if="project.tags.length > 3"
                                            class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-500"
                                        >
                                            +{{ project.tags.length - 3 }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-700"
                                >
                                    <Link
                                        :href="projectShow.url(project.slug)"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        View Details →
                                    </Link>

                                    <div class="flex items-center space-x-3">
                                        <a
                                            v-if="project.github_url"
                                            :href="project.github_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                            title="View Source"
                                        >
                                            <svg
                                                class="h-5 w-5"
                                                fill="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    d="M12 0C5.37 0 0 5.37 0 12 0 17.31 3.435 21.795 8.205 23.385 8.805 23.49 9.03 23.13 9.03 22.815 9.03 22.53 9.015 21.585 9.015 20.055 5.67 20.76 4.965 18.795 4.965 18.795 4.425 17.46 3.63 17.1 3.63 17.1 2.535 16.38 3.72 16.395 3.72 16.395 4.92 16.65 5.555 17.955 5.555 17.955 6.63 19.935 8.385 19.395 9.075 19.065 9.18 18.27 9.495 17.7 9.84 17.385 7.17 17.025 4.35 15.66 4.35 11.145 4.35 9.87 4.815 8.82 5.595 7.98 5.46 7.59 6.45 5.115 6.45 5.115 7.44 4.8 12 6.345 16.56 4.8 17.55 5.115 17.55 5.115 18.54 7.59 18.405 7.98 19.185 8.82 19.65 9.87 19.65 11.145 19.65 15.66 16.83 17.025 14.16 17.385 14.505 17.7 14.82 18.27 14.925 19.065 15.615 19.395 17.37 19.935 18.445 17.955 19.08 16.65 19.335 16.395 20.52 16.38 19.425 17.1 18.63 17.46 18.09 18.795 18.09 18.795 17.385 20.76 14.67 20.055 14.67 21.585 14.655 22.53 14.655 22.815 14.88 23.13 15.48 23.385 20.25 21.795 23.685 17.31 23.685 12 23.685 5.37 18.315 0 11.685 0Z"
                                                />
                                            </svg>
                                        </a>
                                        <a
                                            v-if="
                                                project.demo_url || project.url
                                            "
                                            :href="
                                                project.demo_url ||
                                                project.url ||
                                                '#'
                                            "
                                            target="_blank"
                                            rel="noopener"
                                            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                            title="View Live Demo"
                                        >
                                            <svg
                                                class="h-5 w-5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                                />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="projects.last_page > 1"
                        class="flex items-center justify-center space-x-2 pt-8"
                    >
                        <Link
                            v-if="projects.prev_page_url"
                            :href="projects.prev_page_url"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700"
                        >
                            Previous
                        </Link>

                        <template
                            v-for="link in projects.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="
                                    link.url &&
                                    !['Previous', 'Next'].includes(link.label)
                                "
                                :href="link.url"
                                :class="[
                                    'rounded-lg border px-3 py-2 text-sm',
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700',
                                ]"
                            >
                                {{ link.label }}
                            </Link>
                            <span
                                v-else-if="
                                    !link.url &&
                                    !['Previous', 'Next'].includes(link.label)
                                "
                                class="px-3 py-2 text-sm text-gray-400"
                            >
                                {{ link.label }}
                            </span>
                        </template>

                        <Link
                            v-if="projects.next_page_url"
                            :href="projects.next_page_url"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700"
                        >
                            Next
                        </Link>
                    </div>
                </div>

                <div v-else class="py-12 text-center">
                    <AppWindowMac class="mx-auto h-12 w-12 text-gray-400" />
                    <h3
                        class="mt-2 text-sm font-medium text-gray-900 dark:text-white"
                    >
                        No projects added yet.
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{
                            hasActiveFilters
                                ? 'Try adjusting the filters or search terms.'
                                : 'Someone should really add their projects here... soon.'
                        }}
                    </p>
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                    >
                        Clear all filters
                    </button>
                </div>
            </ScrollArea>
        </main>
    </GuestLayout>
</template>

<style lang="scss" scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
