<script setup lang="ts">
import { SeoHead } from '@/components';
import { MobilePageHeader } from '@/components/page';
import { ScrollArea } from '@/components/ui/scroll-area';
import { GuestLayout } from '@/layouts';
import { index as blogIndex, show as blogShow } from '@/routes/blog';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Clock } from 'lucide-vue-next';

const props = defineProps<{
    series: BlogSeriesDetail;
}>();

const formatDate = (dateString: string | null) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getPostUrl = (post: BlogSeriesDetail['posts'][number]) => {
    if (post.category) {
        return blogShow.url({
            category: post.category.slug,
            post: post.slug,
        });
    }
    return '#';
};
</script>

<template>
    <GuestLayout>
        <SeoHead
            :title="`Series: ${series.title}`"
            :description="
                series.description || `A blog series: ${series.title}`
            "
        />

        <main
            class="relative flex h-full w-full flex-auto flex-col overflow-hidden"
        >
            <ScrollArea class="h-[calc(100vh-150px)] w-full">
                <MobilePageHeader>
                    <Link
                        :href="blogIndex.url()"
                        class="text-[#43D9AD] hover:underline"
                    >
                        _blog
                    </Link>
                    <span class="mx-2 text-[#607B96]">/</span>
                    <span class="text-white">{{ series.title }}</span>
                </MobilePageHeader>

                <div class="mx-auto w-full max-w-4xl px-6 py-8">
                    <nav class="mb-6 hidden text-sm lg:block">
                        <Link
                            :href="blogIndex.url()"
                            class="text-[#43D9AD] hover:underline"
                        >
                            Blog
                        </Link>
                        <span class="mx-2 text-[#607B96]">/</span>
                        <span class="text-[#607B96]">Series</span>
                        <span class="mx-2 text-[#607B96]">/</span>
                        <span class="text-[#607B96]">{{ series.title }}</span>
                    </nav>

                    <div
                        v-if="series.featured_image"
                        class="mb-6 overflow-hidden rounded-lg"
                    >
                        <img
                            :src="`/img/${series.featured_image}`"
                            :alt="series.title"
                            class="h-auto w-full object-cover"
                        />
                    </div>

                    <h1 class="mb-3 text-3xl font-bold text-white">
                        {{ series.title }}
                    </h1>

                    <p v-if="series.description" class="mb-8 text-[#607B96]">
                        {{ series.description }}
                    </p>

                    <p class="mb-6 text-sm text-[#607B96]">
                        {{ series.posts.length }}
                        {{ series.posts.length === 1 ? 'post' : 'posts' }}
                        in this series
                    </p>

                    <ol class="space-y-4">
                        <li
                            v-for="(post, index) in series.posts"
                            :key="post.id"
                        >
                            <Link
                                :href="getPostUrl(post)"
                                class="group flex gap-4 rounded-lg border border-[#1E2D3D] bg-[#011221] p-4 transition-all hover:border-[#607B96]/50"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#1E2D3D] text-sm font-medium text-[#607B96]"
                                >
                                    {{ index + 1 }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3
                                        class="mb-1 font-medium text-white transition-colors group-hover:text-[#FEA55F]"
                                    >
                                        {{ post.title }}
                                    </h3>
                                    <p
                                        v-if="post.excerpt"
                                        class="mb-2 line-clamp-2 text-sm text-[#607B96]"
                                    >
                                        {{ post.excerpt }}
                                    </p>
                                    <div
                                        class="flex items-center gap-3 text-xs text-[#607B96]"
                                    >
                                        <span
                                            v-if="post.category"
                                            class="rounded-full px-2 py-0.5 text-xs"
                                            :style="{
                                                backgroundColor:
                                                    (post.category.color ||
                                                        '#607B96') + '20',
                                                color:
                                                    post.category.color ||
                                                    '#607B96',
                                            }"
                                        >
                                            {{ post.category.name }}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <Clock class="h-3 w-3" />
                                            {{ post.reading_time }} min
                                        </span>
                                        <span v-if="post.published_at">
                                            {{ formatDate(post.published_at) }}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        </li>
                    </ol>

                    <div
                        v-if="series.posts.length === 0"
                        class="py-12 text-center"
                    >
                        <BookOpen class="mx-auto h-12 w-12 text-[#607B96]" />
                        <h3 class="mt-4 text-sm font-medium text-white">
                            No published posts yet
                        </h3>
                        <p class="mt-1 text-sm text-[#607B96]">
                            Check back soon for new posts in this series.
                        </p>
                    </div>

                    <div class="mt-8 text-center">
                        <Link
                            :href="blogIndex.url()"
                            class="inline-flex items-center gap-2 rounded-lg border border-[#1E2D3D] bg-[#011221] px-4 py-2 text-sm text-[#607B96] transition-colors hover:text-white"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back to Blog
                        </Link>
                    </div>
                </div>
            </ScrollArea>
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
</style>
