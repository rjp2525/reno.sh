<script setup lang="ts">
import { SeoHead, TableOfContents } from '@/components';
import { MobilePageHeader } from '@/components/page';
import { ScrollArea } from '@/components/ui/scroll-area';
import { GuestLayout } from '@/layouts';
import BlockRenderer from '@/pages/about/partials/BlockRenderer.vue';
import { index as blogIndex } from '@/routes/blog';
import { category as blogCategoryRoute } from '@/routes/blog';
import { show as blogShow } from '@/routes/blog';
import { home } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    Calendar,
    Check,
    Clock,
    Link2,
    Share2,
    Tag,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    post: BlogPost;
    tableOfContents: TocEntry[];
    relatedPosts: BlogPostSummary[];
    seriesPosts: BlogSeriesPost[] | null;
    olderPost: BlogAdjacentPost | null;
    newerPost: BlogAdjacentPost | null;
}>();

const page = usePage();
const appUrl = computed(() => page.props.appUrl as string);
const shareUrlCopied = ref(false);

const ogImage = computed(() => {
    const img = props.post.og_image || props.post.featured_image;
    if (!img) return undefined;
    if (img.startsWith('http')) return img;
    return `${appUrl.value}/storage/${img}`;
});

const postUrl = computed(() => `${appUrl.value}${page.url}`);

const kebabSlug = computed(() => {
    return props.post.title
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, '')
        .replace(/\s+/g, '-');
});

const sharePost = async () => {
    if (navigator.share) {
        try {
            await navigator.share({
                title: props.post.title,
                text: props.post.excerpt || '',
                url: postUrl.value,
            });
        } catch {
            // user cancelled share
        }
    } else {
        await navigator.clipboard.writeText(postUrl.value);
        shareUrlCopied.value = true;
        setTimeout(() => {
            shareUrlCopied.value = false;
        }, 2000);
    }
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const currentSeriesIndex = computed(() => {
    if (!props.seriesPosts) return -1;
    return props.seriesPosts.findIndex((p) => p.is_current);
});

const prevPost = computed(() => {
    if (!props.seriesPosts || currentSeriesIndex.value <= 0) return null;
    return props.seriesPosts[currentSeriesIndex.value - 1];
});

const nextPost = computed(() => {
    if (
        !props.seriesPosts ||
        currentSeriesIndex.value < 0 ||
        currentSeriesIndex.value >= props.seriesPosts.length - 1
    )
        return null;
    return props.seriesPosts[currentSeriesIndex.value + 1];
});

const getSeriesPostUrl = (post: BlogSeriesPost) => {
    if (post.category_slug) {
        return blogShow.url({
            category: post.category_slug,
            post: post.slug,
        });
    }
    return blogIndex.url();
};

const getRelatedPostUrl = (post: BlogPostSummary) => {
    if (post.category) {
        return blogShow.url({
            category: post.category.slug,
            post: post.slug,
        });
    }
    return blogIndex.url();
};

const getAdjacentPostUrl = (post: BlogAdjacentPost) => {
    if (post.category_slug) {
        return blogShow.url({
            category: post.category_slug,
            post: post.slug,
        });
    }
    return `/blog/post/${post.slug}`;
};

const jsonLd = computed(() => {
    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BlogPosting',
        headline: props.post.title,
        description: props.post.meta_description || props.post.excerpt || '',
        image: ogImage.value,
        datePublished: props.post.published_at,
        dateModified: props.post.updated_at || props.post.published_at,
        author: {
            '@type': 'Person',
            name: 'Reno Philibert',
        },
        wordCount: props.post.reading_time * 200,
        timeRequired: `PT${props.post.reading_time}M`,
    });
});
</script>

<template>
    <GuestLayout>
        <SeoHead
            :title="post.meta_title || post.title"
            :description="post.meta_description || post.excerpt"
            :image="ogImage"
            :keywords="post.meta_keywords?.join(', ')"
            type="article"
        />

        <Head>
            <component is="script" type="application/ld+json" v-html="jsonLd" />
        </Head>

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
                    <Link
                        v-if="post.category"
                        :href="
                            blogCategoryRoute.url({
                                category: post.category.slug,
                            })
                        "
                        class="text-[#43D9AD] hover:underline"
                    >
                        {{ post.category.slug }}
                    </Link>
                    <span v-if="post.category" class="mx-2 text-[#607B96]"
                        >/</span
                    >
                    <span class="text-white">{{ kebabSlug }}</span>
                </MobilePageHeader>

                <div
                    class="hidden items-center gap-1 border-b border-[#1E2D3D] px-4 py-3 text-xs text-[#607B96] sm:px-6 sm:text-sm lg:flex"
                >
                    <Link
                        :href="home.url()"
                        class="transition-colors hover:text-white"
                    >
                        root
                    </Link>
                    <span>/</span>
                    <Link
                        :href="blogIndex.url()"
                        class="transition-colors hover:text-white"
                    >
                        blog
                    </Link>
                    <template v-if="post.category">
                        <span>/</span>
                        <Link
                            :href="
                                blogCategoryRoute.url({
                                    category: post.category.slug,
                                })
                            "
                            class="transition-colors hover:text-white"
                        >
                            {{ post.category.slug }}
                        </Link>
                    </template>
                    <span>/</span>
                    <span class="truncate text-white">{{ kebabSlug }}</span>
                </div>

                <div v-if="post.featured_image" class="relative">
                    <div class="relative max-h-[350px] overflow-hidden">
                        <img
                            :src="`/storage/${post.featured_image}`"
                            :alt="post.title"
                            class="h-full w-full object-cover object-center"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-[#011627] via-[#011627]/70 to-transparent"
                        />
                        <div
                            class="absolute bottom-0 left-0 right-0 p-6"
                        >
                            <div class="mx-auto w-full max-w-5xl">
                                <div v-if="post.category" class="mb-2">
                                    <Link
                                        :href="
                                            blogCategoryRoute.url({
                                                category:
                                                    post.category.slug,
                                            })
                                        "
                                        class="rounded-full px-3 py-1 text-xs font-medium transition-opacity hover:opacity-80"
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
                                    </Link>
                                </div>

                                <h1
                                    class="mb-3 text-3xl font-bold text-white lg:text-4xl"
                                >
                                    {{ post.title }}
                                </h1>

                                <div
                                    class="flex w-full items-center justify-between text-sm text-white/70"
                                >
                                    <div class="flex flex-wrap items-center gap-4">
                                        <span
                                            v-if="post.published_at"
                                            class="flex items-center gap-1"
                                        >
                                            <Calendar class="h-4 w-4" />
                                            {{
                                                formatDate(
                                                    post.published_at,
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="flex items-center gap-1"
                                        >
                                            <Clock class="h-4 w-4" />
                                            {{ post.reading_time }} min read
                                        </span>
                                    </div>

                                    <button
                                        @click="sharePost"
                                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1 text-xs text-white/70 backdrop-blur-sm transition-colors hover:bg-white/20 hover:text-white"
                                        :title="
                                            shareUrlCopied
                                                ? 'Link copied!'
                                                : 'Share post'
                                        "
                                    >
                                        <Check
                                            v-if="shareUrlCopied"
                                            class="h-3.5 w-3.5 text-[#43D9AD]"
                                        />
                                        <Share2 v-else class="h-3.5 w-3.5" />
                                        {{ shareUrlCopied ? 'Copied!' : 'Share' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="mx-auto w-full max-w-5xl px-6 pt-8">
                    <header class="mb-8">
                        <div v-if="post.category" class="mb-3">
                            <Link
                                :href="
                                    blogCategoryRoute.url({
                                        category: post.category.slug,
                                    })
                                "
                                class="rounded-full px-3 py-1 text-xs font-medium transition-opacity hover:opacity-80"
                                :style="{
                                    backgroundColor:
                                        (post.category.color ||
                                            '#607B96') + '30',
                                    color:
                                        post.category.color || '#607B96',
                                }"
                            >
                                {{ post.category.name }}
                            </Link>
                        </div>

                        <h1
                            class="mb-4 text-3xl font-bold text-white lg:text-4xl"
                        >
                            {{ post.title }}
                        </h1>

                        <div
                            class="flex w-full items-center justify-between text-sm text-[#607B96]"
                        >
                            <div class="flex flex-wrap items-center gap-4">
                                <span
                                    v-if="post.published_at"
                                    class="flex items-center gap-1"
                                >
                                    <Calendar class="h-4 w-4" />
                                    {{ formatDate(post.published_at) }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Clock class="h-4 w-4" />
                                    {{ post.reading_time }} min read
                                </span>
                            </div>

                            <button
                                @click="sharePost"
                                class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-[#1E2D3D] bg-[#011221] px-2.5 py-1 text-xs text-[#607B96] transition-colors hover:border-[#607B96]/50 hover:text-white"
                                :title="
                                    shareUrlCopied
                                        ? 'Link copied!'
                                        : 'Share post'
                                "
                            >
                                <Check
                                    v-if="shareUrlCopied"
                                    class="h-3.5 w-3.5 text-[#43D9AD]"
                                />
                                <Share2 v-else class="h-3.5 w-3.5" />
                                {{ shareUrlCopied ? 'Copied!' : 'Share' }}
                            </button>
                        </div>
                    </header>
                </div>

                <div class="mx-auto w-full max-w-5xl px-6 py-8">
                    <div :class="tableOfContents.length ? 'grid gap-8 lg:grid-cols-[1fr_250px]' : ''">
                        <article class="min-w-0">
                            <div
                                v-if="seriesPosts && post.series"
                                class="mb-8 rounded-lg border border-[#1E2D3D] bg-[#011221] p-4"
                            >
                                <h3
                                    class="mb-2 text-sm font-semibold text-white"
                                >
                                    Series: {{ post.series.title }}
                                </h3>
                                <p class="mb-3 text-xs text-[#607B96]">
                                    Part {{ currentSeriesIndex + 1 }} of
                                    {{ seriesPosts.length }}
                                </p>
                                <div
                                    class="flex items-center justify-between gap-4"
                                >
                                    <Link
                                        v-if="prevPost"
                                        :href="getSeriesPostUrl(prevPost)"
                                        class="flex items-center gap-1 text-sm text-[#43D9AD] hover:underline"
                                    >
                                        <ArrowLeft class="h-3 w-3" />
                                        Previous
                                    </Link>
                                    <span v-else></span>
                                    <Link
                                        v-if="nextPost"
                                        :href="getSeriesPostUrl(nextPost)"
                                        class="flex items-center gap-1 text-sm text-[#43D9AD] hover:underline"
                                    >
                                        Next
                                        <ArrowRight class="h-3 w-3" />
                                    </Link>
                                </div>
                            </div>

                            <BlockRenderer
                                :content="post.content"
                                :scrollable="false"
                            />

                            <div
                                class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-[#1E2D3D] pt-6"
                            >
                                <div
                                    v-if="post.tags.length"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <Tag class="h-4 w-4 text-[#607B96]" />
                                    <span
                                        v-for="tag in post.tags"
                                        :key="tag.id"
                                        class="rounded-full border border-[#1E2D3D] bg-[#011221] px-3 py-1 text-xs text-[#607B96]"
                                    >
                                        #{{ tag.name }}
                                    </span>
                                </div>

                                <button
                                    @click="sharePost"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-[#1E2D3D] bg-[#011221] px-3 py-1.5 text-xs text-[#607B96] transition-colors hover:border-[#607B96]/50 hover:text-white"
                                >
                                    <Check
                                        v-if="shareUrlCopied"
                                        class="h-3.5 w-3.5 text-[#43D9AD]"
                                    />
                                    <Link2 v-else class="h-3.5 w-3.5" />
                                    {{
                                        shareUrlCopied
                                            ? 'Link copied!'
                                            : 'Share'
                                    }}
                                </button>
                            </div>
                        </article>

                        <aside class="hidden lg:block">
                            <TableOfContents :entries="tableOfContents" />
                        </aside>
                    </div>

                    <div
                        v-if="relatedPosts.length"
                        class="mt-12 border-t border-[#1E2D3D] pt-8"
                    >
                        <h2 class="mb-6 text-xl font-bold text-white">
                            Related Posts
                        </h2>
                        <div class="grid gap-6 md:grid-cols-3">
                            <Link
                                v-for="related in relatedPosts"
                                :key="related.id"
                                :href="getRelatedPostUrl(related)"
                                class="group overflow-hidden rounded-lg border border-[#1E2D3D] bg-[#011221] transition-all hover:border-[#607B96]/50"
                            >
                                <div
                                    class="relative aspect-video overflow-hidden bg-[#0a1628]"
                                >
                                    <img
                                        v-if="related.featured_image"
                                        :src="`/storage/${related.featured_image}`"
                                        :alt="related.title"
                                        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full items-center justify-center"
                                    >
                                        <BookOpen
                                            class="h-6 w-6 text-[#607B96]"
                                        />
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h3
                                        class="mb-1 line-clamp-2 font-medium text-white transition-colors group-hover:text-[#FEA55F]"
                                    >
                                        {{ related.title }}
                                    </h3>
                                    <p
                                        v-if="related.excerpt"
                                        class="mb-2 line-clamp-2 text-sm text-[#607B96]"
                                    >
                                        {{ related.excerpt }}
                                    </p>
                                    <div
                                        class="flex items-center gap-2 text-xs text-[#607B96]"
                                    >
                                        <Clock class="h-3 w-3" />
                                        {{ related.reading_time }} min
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="olderPost || newerPost"
                        class="mt-8 flex items-stretch justify-between gap-4 border-t border-[#1E2D3D] pt-8"
                    >
                        <Link
                            v-if="olderPost"
                            :href="getAdjacentPostUrl(olderPost)"
                            class="group flex min-w-0 flex-1 items-center gap-3 rounded-lg border border-[#1E2D3D] bg-[#011221] p-4 transition-colors hover:border-[#607B96]/50"
                        >
                            <ArrowLeft
                                class="h-5 w-5 shrink-0 text-[#607B96] transition-colors group-hover:text-white"
                            />
                            <div class="min-w-0">
                                <span
                                    class="text-xs text-[#607B96]"
                                    >older post</span
                                >
                                <p
                                    class="truncate text-sm font-medium text-white transition-colors group-hover:text-[#FEA55F]"
                                >
                                    {{ olderPost.title }}
                                </p>
                            </div>
                        </Link>
                        <div v-else class="flex-1" />

                        <Link
                            v-if="newerPost"
                            :href="getAdjacentPostUrl(newerPost)"
                            class="group flex min-w-0 flex-1 items-center justify-end gap-3 rounded-lg border border-[#1E2D3D] bg-[#011221] p-4 text-right transition-colors hover:border-[#607B96]/50"
                        >
                            <div class="min-w-0">
                                <span
                                    class="text-xs text-[#607B96]"
                                    >newer post</span
                                >
                                <p
                                    class="truncate text-sm font-medium text-white transition-colors group-hover:text-[#FEA55F]"
                                >
                                    {{ newerPost.title }}
                                </p>
                            </div>
                            <ArrowRight
                                class="h-5 w-5 shrink-0 text-[#607B96] transition-colors group-hover:text-white"
                            />
                        </Link>
                        <div v-else class="flex-1" />
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
