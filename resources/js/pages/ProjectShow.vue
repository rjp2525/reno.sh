<script setup lang="ts">
import { MobilePageHeader } from '@/components/page';
import { ScrollArea } from '@/components/ui/scroll-area';
import { GuestLayout } from '@/layouts';
import { projects as projectsRoute } from '@/routes';
import { show as projectShow } from '@/routes/projects';
import { SeoHead } from '@/components';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, inject, ref } from 'vue';

const isMobile = inject('isMobile', ref(false));

const props = defineProps<{
    project: Project;
    relatedProjects: Project[];
}>();

const formatDate = (dateString: string | null) => {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getDateRange = (project: Project) => {
    if (!project.start_date && !project.is_ongoing) return null;

    const start = formatDate(project.start_date);
    if (project.is_ongoing) {
        return `${start} - Present`;
    } else if (project.end_date) {
        return `${start} - ${formatDate(project.end_date)}`;
    }
    return start;
};

const page = usePage();
const appUrl = computed(() => page.props.appUrl as string);

const ogImage = computed(() => {
    const img = props.project.og_image || props.project.featured_image;
    if (!img) return undefined;
    if (img.startsWith('http')) return img;
    return `${appUrl.value}/storage/${img}`;
});

const primaryTechnologies = props.project.technologies.filter(
    (tech) => tech.pivot?.is_primary,
);
const otherTechnologies = props.project.technologies.filter(
    (tech) => !tech.pivot?.is_primary,
);
</script>

<template>
    <GuestLayout>
        <SeoHead
            :title="project.meta_title || project.title"
            :description="project.meta_description || project.summary"
            :image="ogImage"
            :keywords="project.meta_keywords?.join(', ')"
            type="article"
        />

        <main
            class="relative flex h-full w-full flex-auto flex-col overflow-hidden"
        >
            <ScrollArea class="h-[calc(100vh-150px)] w-full p-6">
                <MobilePageHeader>
                    <Link
                        :href="projectsRoute.url()"
                        class="text-blue-600 hover:underline dark:text-blue-400"
                    >
                        _projects
                    </Link>
                    <span class="mx-2 text-gray-400">/</span>
                    <span>{{ project.title }}</span>
                </MobilePageHeader>

                <div class="flex-1 overflow-y-auto">
                    <div class="relative">
                        <div
                            v-if="project.featured_image"
                            class="relative aspect-[16/9] overflow-hidden lg:aspect-[21/9]"
                        >
                            <img
                                :src="`/storage/${project.featured_image}`"
                                :alt="project.title"
                                class="h-full w-full object-cover"
                            />
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black to-transparent"
                            />
                        </div>

                        <div
                            class="relative px-6"
                            :class="
                                project.featured_image
                                    ? 'lg:relative lg:z-10 lg:-mt-32'
                                    : ''
                            "
                        >
                            <div class="w-full">
                                <div
                                    class="mb-4 flex items-center justify-between"
                                >
                                    <nav class="text-sm">
                                        <Link
                                            :href="projectsRoute.url()"
                                            class="text-blue-600 hover:underline dark:text-blue-400"
                                        >
                                            Projects
                                        </Link>
                                        <span class="mx-2 text-gray-400"
                                            >/</span
                                        >
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                            >{{ project.title }}</span
                                        >
                                    </nav>

                                    <div class="flex items-center space-x-2">
                                        <span
                                            v-if="project.is_featured"
                                            class="rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300"
                                        >
                                            Featured
                                        </span>
                                        <span
                                            v-if="project.type"
                                            class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            {{ project.type.replace('_', ' ') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div
                                        :class="
                                            project.featured_image
                                                ? 'text-white'
                                                : ''
                                        "
                                    >
                                        <h1
                                            class="mb-4 text-3xl font-bold lg:text-5xl"
                                        >
                                            {{ project.title }}
                                        </h1>

                                        <p
                                            v-if="project.summary"
                                            class="mb-6 max-w-5xl text-lg lg:text-xl"
                                            :class="
                                                project.featured_image
                                                    ? 'text-gray-100'
                                                    : 'text-gray-600 dark:text-gray-400'
                                            "
                                        >
                                            {{ project.summary }}
                                        </p>

                                        <div
                                            v-if="getDateRange(project)"
                                            class="mb-6 text-sm"
                                            :class="
                                                project.featured_image
                                                    ? 'text-gray-200'
                                                    : 'text-gray-500 dark:text-gray-400'
                                            "
                                        >
                                            {{ getDateRange(project) }}
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <a
                                            v-if="
                                                project.demo_url || project.url
                                            "
                                            :href="
                                                project.demo_url || project.url
                                            "
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
                                        >
                                            <svg
                                                class="mr-2 h-4 w-4"
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
                                            View Live Demo
                                        </a>

                                        <a
                                            v-if="project.github_url"
                                            :href="project.github_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 font-medium text-white transition-colors hover:bg-gray-900"
                                        >
                                            <svg
                                                class="mr-2 h-4 w-4"
                                                fill="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    d="M12 0C5.37 0 0 5.37 0 12 0 17.31 3.435 21.795 8.205 23.385 8.805 23.49 9.03 23.13 9.03 22.815 9.03 22.53 9.015 21.585 9.015 20.055 5.67 20.76 4.965 18.795 4.965 18.795 4.425 17.46 3.63 17.1 3.63 17.1 2.535 16.38 3.72 16.395 3.72 16.395 4.92 16.65 5.555 17.955 5.555 17.955 6.63 19.935 8.385 19.395 9.075 19.065 9.18 18.27 9.495 17.7 9.84 17.385 7.17 17.025 4.35 15.66 4.35 11.145 4.35 9.87 4.815 8.82 5.595 7.98 5.46 7.59 6.45 5.115 6.45 5.115 7.44 4.8 12 6.345 16.56 4.8 17.55 5.115 17.55 5.115 18.54 7.59 18.405 7.98 19.185 8.82 19.65 9.87 19.65 11.145 19.65 15.66 16.83 17.025 14.16 17.385 14.505 17.7 14.82 18.27 14.925 19.065 15.615 19.395 17.37 19.935 18.445 17.955 19.08 16.65 19.335 16.395 20.52 16.38 19.425 17.1 18.63 17.46 18.09 18.795 18.09 18.795 17.385 20.76 14.67 20.055 14.67 21.585 14.655 22.53 14.655 22.815 14.88 23.13 15.48 23.385 20.25 21.795 23.685 17.31 23.685 12 23.685 5.37 18.315 0 11.685 0Z"
                                                />
                                            </svg>
                                            View Source
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-8">
                        <div class="w-full">
                            <div class="grid gap-8 lg:grid-cols-3">
                                <div class="space-y-8 lg:col-span-2">
                                    <div v-if="project.description">
                                        <h2
                                            class="mb-4 text-2xl font-bold text-gray-900 dark:text-white"
                                        >
                                            About this project
                                        </h2>
                                        <div
                                            class="prose prose-gray dark:prose-invert max-w-none"
                                        >
                                            <p
                                                class="leading-relaxed whitespace-pre-wrap text-gray-600 dark:text-gray-400"
                                            >
                                                {{ project.description }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        v-if="
                                            project.gallery &&
                                            project.gallery.length
                                        "
                                    >
                                        <h2
                                            class="mb-4 text-2xl font-bold text-gray-900 dark:text-white"
                                        >
                                            Gallery
                                        </h2>
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div
                                                v-for="(
                                                    image, index
                                                ) in project.gallery"
                                                :key="index"
                                                class="aspect-video overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800"
                                            >
                                                <img
                                                    :src="`/storage/${image.image}`"
                                                    :alt="`${project.title} screenshot ${index + 1}`"
                                                    class="h-full w-full object-cover transition-transform duration-200 hover:scale-105"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="project.technologies.length">
                                        <h2
                                            class="mb-4 text-2xl font-bold text-gray-900 dark:text-white"
                                        >
                                            Technologies Used
                                        </h2>

                                        <div
                                            v-if="primaryTechnologies.length"
                                            class="mb-6"
                                        >
                                            <h3
                                                class="mb-3 text-lg font-semibold text-gray-800 dark:text-gray-200"
                                            >
                                                Primary Technologies
                                            </h3>
                                            <div
                                                class="grid gap-3 sm:grid-cols-2"
                                            >
                                                <div
                                                    v-for="tech in primaryTechnologies"
                                                    :key="tech.id"
                                                    class="flex items-center rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800"
                                                >
                                                    <div class="flex-1">
                                                        <div
                                                            class="font-medium text-gray-900 dark:text-white"
                                                        >
                                                            {{ tech.name }}
                                                        </div>
                                                        <div
                                                            v-if="tech.category"
                                                            class="text-sm text-gray-500 dark:text-gray-400"
                                                        >
                                                            {{ tech.category }}
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-if="
                                                            tech.pivot
                                                                ?.proficiency_level
                                                        "
                                                        class="rounded-full bg-green-100 px-2 py-1 text-xs text-green-800 dark:bg-green-900 dark:text-green-200"
                                                    >
                                                        {{
                                                            tech.pivot
                                                                .proficiency_level
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="otherTechnologies.length">
                                            <h3
                                                class="mb-3 text-lg font-semibold text-gray-800 dark:text-gray-200"
                                            >
                                                Additional Technologies
                                            </h3>
                                            <div class="flex flex-wrap gap-2">
                                                <span
                                                    v-for="tech in otherTechnologies"
                                                    :key="tech.id"
                                                    class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                                    :style="
                                                        tech.color
                                                            ? {
                                                                  backgroundColor:
                                                                      tech.color +
                                                                      '20',
                                                                  color: tech.color,
                                                              }
                                                            : {}
                                                    "
                                                >
                                                    {{ tech.name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <div
                                        class="rounded-lg bg-gray-50 p-6 dark:bg-gray-800"
                                    >
                                        <h3
                                            class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            Project Details
                                        </h3>
                                        <dl class="space-y-3">
                                            <div v-if="project.type">
                                                <dt
                                                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                                >
                                                    Type
                                                </dt>
                                                <dd
                                                    class="text-sm text-gray-900 capitalize dark:text-white"
                                                >
                                                    {{
                                                        project.type.replace(
                                                            '_',
                                                            ' ',
                                                        )
                                                    }}
                                                </dd>
                                            </div>

                                            <div v-if="project.status">
                                                <dt
                                                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                                >
                                                    Status
                                                </dt>
                                                <dd
                                                    class="text-sm text-gray-900 capitalize dark:text-white"
                                                >
                                                    {{
                                                        project.status.replace(
                                                            '_',
                                                            ' ',
                                                        )
                                                    }}
                                                </dd>
                                            </div>

                                            <div v-if="getDateRange(project)">
                                                <dt
                                                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                                >
                                                    Timeline
                                                </dt>
                                                <dd
                                                    class="text-sm text-gray-900 dark:text-white"
                                                >
                                                    {{ getDateRange(project) }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <div
                                        v-if="project.tags.length"
                                        class="rounded-lg bg-gray-50 p-6 dark:bg-gray-800"
                                    >
                                        <h3
                                            class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            Tags
                                        </h3>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="tag in project.tags"
                                                :key="tag.id"
                                                class="rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                            >
                                                {{ tag.name.en }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-lg bg-gray-50 p-6 dark:bg-gray-800"
                                    >
                                        <h3
                                            class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            Quick Actions
                                        </h3>
                                        <div class="space-y-3">
                                            <a
                                                v-if="
                                                    project.demo_url ||
                                                    project.url
                                                "
                                                :href="
                                                    project.demo_url ||
                                                    project.url
                                                "
                                                target="_blank"
                                                rel="noopener"
                                                class="flex w-full items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
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
                                                Visit Live Site
                                            </a>

                                            <a
                                                v-if="project.github_url"
                                                :href="project.github_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="flex w-full items-center justify-center rounded-lg bg-gray-800 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-900"
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
                                                    fill="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        d="M12 0C5.37 0 0 5.37 0 12 0 17.31 3.435 21.795 8.205 23.385 8.805 23.49 9.03 23.13 9.03 22.815 9.03 22.53 9.015 21.585 9.015 20.055 5.67 20.76 4.965 18.795 4.965 18.795 4.425 17.46 3.63 17.1 3.63 17.1 2.535 16.38 3.72 16.395 3.72 16.395 4.92 16.65 5.555 17.955 5.555 17.955 6.63 19.935 8.385 19.395 9.075 19.065 9.18 18.27 9.495 17.7 9.84 17.385 7.17 17.025 4.35 15.66 4.35 11.145 4.35 9.87 4.815 8.82 5.595 7.98 5.46 7.59 6.45 5.115 6.45 5.115 7.44 4.8 12 6.345 16.56 4.8 17.55 5.115 17.55 5.115 18.54 7.59 18.405 7.98 19.185 8.82 19.65 9.87 19.65 11.145 19.65 15.66 16.83 17.025 14.16 17.385 14.505 17.7 14.82 18.27 14.925 19.065 15.615 19.395 17.37 19.935 18.445 17.955 19.08 16.65 19.335 16.395 20.52 16.38 19.425 17.1 18.63 17.46 18.09 18.795 18.09 18.795 17.385 20.76 14.67 20.055 14.67 21.585 14.655 22.53 14.655 22.815 14.88 23.13 15.48 23.385 20.25 21.795 23.685 17.31 23.685 12 23.685 5.37 18.315 0 11.685 0Z"
                                                    />
                                                </svg>
                                                View Source Code
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="relatedProjects.length"
                        class="bg-gray-50 px-6 py-8 dark:bg-gray-900"
                    >
                        <div class="w-full">
                            <h2
                                class="mb-6 text-2xl font-bold text-gray-900 dark:text-white"
                            >
                                Related Projects
                            </h2>
                            <div class="grid gap-6 md:grid-cols-3">
                                <div
                                    v-for="relatedProject in relatedProjects"
                                    :key="relatedProject.id"
                                    class="group overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <div
                                        class="relative aspect-video overflow-hidden bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-700 dark:to-gray-600"
                                    >
                                        <img
                                            v-if="relatedProject.featured_image"
                                            :src="relatedProject.featured_image"
                                            :alt="relatedProject.title"
                                            class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full items-center justify-center"
                                        >
                                            <svg
                                                class="h-8 w-8 text-gray-400"
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

                                    <div class="p-4">
                                        <h3
                                            class="mb-2 font-semibold text-gray-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400"
                                        >
                                            {{ relatedProject.title }}
                                        </h3>
                                        <p
                                            class="mb-3 line-clamp-2 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            {{
                                                relatedProject.summary ||
                                                relatedProject.description?.substring(
                                                    0,
                                                    100,
                                                ) + '...'
                                            }}
                                        </p>
                                        <Link
                                            :href="
                                                projectShow.url(
                                                    relatedProject.slug,
                                                )
                                            "
                                            class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            View Project →
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-8 text-center">
                        <Link
                            :href="projectsRoute.url()"
                            class="inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 font-medium text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            <svg
                                class="mr-2 h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                                />
                            </svg>
                            Back to Projects
                        </Link>
                    </div>
                </div>
            </ScrollArea>
        </main>
    </GuestLayout>
</template>

<style lang="scss" scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
