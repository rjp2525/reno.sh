<script setup lang="ts">
import { GuestLayout } from '@/layouts';
import { gallery as galleryRoute, home } from '@/routes';
import { SeoHead } from '@/components';
import { Link } from '@inertiajs/vue3';
import {
    Camera,
    ChevronLeft,
    ChevronRight,
    Instagram,
    MapPin,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    photo: Photo;
    prevPhoto: { slug: string; title: string } | null;
    nextPhoto: { slug: string; title: string } | null;
}>();

const baseGalleryUrl = galleryRoute.url();

const stripHtml = (html: string | null): string => {
    if (!html) return '';
    return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
};

const hasDescription = computed(() => {
    return stripHtml(props.photo.description).length > 0;
});

const plainDescription = computed(() => {
    const text = stripHtml(props.photo.description);
    return text || 'Photo by Reno Philibert';
});

const kebabCaseTitle = computed(() => {
    return (
        props.photo.title
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, '')
            .replace(/\s+/g, '-') + '.jpg'
    );
});

const formattedDate = computed(() => {
    if (!props.photo.taken_at) return null;
    return props.photo.taken_at;
});

const hasLocation = computed(() => {
    return props.photo.gps_latitude && props.photo.gps_longitude;
});

const prevUrl = computed(() =>
    props.prevPhoto ? `${baseGalleryUrl}/${props.prevPhoto.slug}` : null,
);
const nextUrl = computed(() =>
    props.nextPhoto ? `${baseGalleryUrl}/${props.nextPhoto.slug}` : null,
);
</script>

<template>
    <GuestLayout>
        <SeoHead
            :title="`${photo.title} | Gallery`"
            :description="plainDescription"
            :image="photo.og_image_url || photo.web_url"
            type="article"
        />

        <main class="flex h-full w-full flex-auto overflow-hidden">
            <div class="h-full w-full overflow-x-hidden overflow-y-auto">
                <div
                    class="flex flex-wrap items-center gap-1 border-b border-[#1E2D3D] px-4 py-3 text-xs text-[#607B96] sm:px-6 sm:text-sm"
                >
                    <Link
                        :href="home.url()"
                        class="transition-colors hover:text-white"
                    >
                        root
                    </Link>
                    <span>/</span>
                    <Link
                        :href="baseGalleryUrl"
                        class="transition-colors hover:text-white"
                    >
                        photography
                    </Link>
                    <template v-if="photo.category">
                        <span>/</span>
                        <Link
                            :href="`${baseGalleryUrl}?category=${photo.category.slug}`"
                            class="transition-colors hover:text-white"
                        >
                            {{ photo.category.slug }}
                        </Link>
                    </template>
                    <span>/</span>
                    <span class="truncate text-white">{{
                        kebabCaseTitle
                    }}</span>
                </div>

                <div class="w-full max-w-full p-4 sm:p-6">
                    <div class="mb-4 flex w-full flex-col gap-4 lg:flex-row lg:items-start">
                        <div class="min-w-0 max-w-[1280px] lg:flex-1">
                            <div
                                class="overflow-hidden rounded-lg bg-[#011221]"
                                @contextmenu.prevent
                                @dragstart.prevent
                            >
                                <div
                                    v-if="photo.web_url"
                                    class="protected-image"
                                    :style="{
                                        backgroundImage: `url(${photo.web_url})`,
                                        '--aspect-ratio': photo.aspect_ratio,
                                    }"
                                    role="img"
                                    :aria-label="photo.title"
                                />
                                <div
                                    v-else
                                    class="flex aspect-[4/3] items-center justify-center"
                                >
                                    <Camera class="h-16 w-16 text-[#607B96]" />
                                </div>
                            </div>

                            <div
                                v-if="photo.exif.summary"
                                class="mt-3 text-center text-xs text-[#607B96] lg:hidden"
                            >
                                {{ photo.exif.summary }}
                            </div>
                        </div>

                        <div class="hidden w-full max-w-[360px] shrink-0 space-y-3 lg:block">
                            <div
                                class="rounded-lg border border-[#1E2D3D] bg-[#011627]"
                            >
                                <div
                                    class="flex items-center gap-2 border-b border-[#1E2D3D] px-3 py-2"
                                >
                                    <Camera class="h-3 w-3 text-[#E99287]" />
                                    <span class="text-xs font-medium text-white"
                                        >Metadata</span
                                    >
                                </div>
                                <div class="grid grid-cols-2 gap-2 p-3">
                                    <div v-if="photo.exif.camera" class="col-span-2">
                                        <p class="text-[11px] uppercase text-[#607B96]">
                                            Camera
                                        </p>
                                        <p class="truncate text-sm text-white">
                                            {{ photo.exif.camera }}
                                        </p>
                                    </div>
                                    <div v-if="photo.exif.lens" class="col-span-2">
                                        <p class="text-[11px] uppercase text-[#607B96]">
                                            Lens
                                        </p>
                                        <p class="truncate text-sm text-white">
                                            {{ photo.exif.lens }}
                                        </p>
                                    </div>
                                    <div v-if="photo.exif.aperture">
                                        <p class="text-[11px] uppercase text-[#607B96]">
                                            Aperture
                                        </p>
                                        <p class="text-sm text-[#4D5BCE]">
                                            {{ photo.exif.aperture }}
                                        </p>
                                    </div>
                                    <div v-if="photo.exif.iso">
                                        <p class="text-[11px] uppercase text-[#607B96]">
                                            ISO
                                        </p>
                                        <p class="text-sm text-[#E99287]">
                                            {{ photo.exif.iso }}
                                        </p>
                                    </div>
                                    <div v-if="photo.exif.shutter_speed">
                                        <p class="text-[11px] uppercase text-[#607B96]">
                                            Shutter
                                        </p>
                                        <p class="text-sm text-[#4D5BCE]">
                                            {{ photo.exif.shutter_speed }}
                                        </p>
                                    </div>
                                    <div v-if="photo.exif.focal_length">
                                        <p class="text-[11px] uppercase text-[#607B96]">
                                            Focal
                                        </p>
                                        <p class="text-sm text-white">
                                            {{ photo.exif.focal_length }}
                                        </p>
                                    </div>
                                    <div v-if="formattedDate" class="col-span-2">
                                        <p class="text-[11px] uppercase text-[#607B96]">
                                            Date
                                        </p>
                                        <p class="text-sm text-[#E99287]">
                                            {{ formattedDate }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="hasLocation"
                                class="rounded-lg border border-[#1E2D3D] bg-[#011627] p-3"
                            >
                                <div class="flex items-center gap-2">
                                    <MapPin class="h-3.5 w-3.5 text-[#43D9AD]" />
                                    <span class="text-sm text-white"
                                        >Location Available</span
                                    >
                                </div>
                            </div>

                            <div
                                v-if="photo.tags && photo.tags.length"
                                class="rounded-lg border border-[#1E2D3D] bg-[#011627] p-3"
                            >
                                <p class="mb-2 text-[11px] uppercase text-[#607B96]">Tags</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <Link
                                        v-for="tag in photo.tags"
                                        :key="tag.id"
                                        :href="`${baseGalleryUrl}?tags=${tag.slug}`"
                                        class="rounded-full border border-[#1E2D3D] bg-[#011221] px-2.5 py-0.5 text-xs text-[#607B96] transition-colors hover:border-[#607B96] hover:text-white"
                                    >
                                        #{{ tag.name }}
                                    </Link>
                                </div>
                            </div>

                            <a
                                v-if="photo.instagram_link"
                                :href="photo.instagram_link"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-2 rounded-lg border border-[#1E2D3D] bg-[#011627] p-3 transition-colors hover:border-[#E1306C]"
                            >
                                <Instagram class="h-3.5 w-3.5 text-[#E1306C]" />
                                <span class="text-sm text-white"
                                    >View on Instagram</span
                                >
                            </a>
                        </div>
                    </div>

                    <div class="max-w-[1280px]">
                            <h1 class="mb-4 text-xl sm:text-2xl lg:text-3xl">
                                <span class="text-[#4D5BCE]">const </span>
                                <span class="text-white">{{
                                    photo.title.toLowerCase().replace(/[^a-z0-9\s]/g, '').replace(/\s+/g, '_')
                                }}</span>
                            </h1>

                            <div
                                v-if="hasDescription"
                                class="photo-description prose prose-sm prose-invert mb-4 max-w-none sm:mb-6 sm:prose-base"
                                v-html="photo.description"
                            />

                            <div
                                class="mb-4 grid grid-cols-2 gap-3 rounded-lg border border-[#1E2D3D] bg-[#011627] p-3 text-xs sm:grid-cols-4 lg:hidden"
                            >
                                <div v-if="photo.exif.camera">
                                    <p class="text-[#607B96]">Camera</p>
                                    <p class="truncate text-white">
                                        {{ photo.exif.camera }}
                                    </p>
                                </div>
                                <div v-if="photo.exif.aperture">
                                    <p class="text-[#607B96]">Aperture</p>
                                    <p class="text-[#4D5BCE]">
                                        {{ photo.exif.aperture }}
                                    </p>
                                </div>
                                <div v-if="photo.exif.shutter_speed">
                                    <p class="text-[#607B96]">Shutter</p>
                                    <p class="text-[#4D5BCE]">
                                        {{ photo.exif.shutter_speed }}
                                    </p>
                                </div>
                                <div v-if="photo.exif.iso">
                                    <p class="text-[#607B96]">ISO</p>
                                    <p class="text-[#E99287]">
                                        {{ photo.exif.iso }}
                                    </p>
                                </div>
                                <div v-if="formattedDate" class="col-span-2">
                                    <p class="text-[#607B96]">Date</p>
                                    <p class="text-[#E99287]">
                                        {{ formattedDate }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="photo.tags && photo.tags.length"
                                class="mb-4 flex flex-wrap gap-2 lg:hidden"
                            >
                                <Link
                                    v-for="tag in photo.tags"
                                    :key="tag.id"
                                    :href="`${baseGalleryUrl}?tags=${tag.slug}`"
                                    class="rounded-full border border-[#1E2D3D] bg-[#011221] px-3 py-1 text-xs text-[#607B96] transition-colors hover:border-[#607B96] hover:text-white"
                                >
                                    #{{ tag.name }}
                                </Link>
                            </div>

                            <a
                                v-if="photo.instagram_link"
                                :href="photo.instagram_link"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mb-4 flex items-center justify-center gap-2 rounded-lg border border-[#1E2D3D] bg-[#011627] p-3 text-sm transition-colors hover:border-[#E1306C] lg:hidden"
                            >
                                <Instagram class="h-4 w-4 text-[#E1306C]" />
                                <span class="text-white">View on Instagram</span>
                            </a>

                            <div
                            v-if="prevUrl || nextUrl"
                                class="mb-4 flex items-center justify-between border-y border-[#1E2D3D] py-3 sm:mb-6 sm:py-4"
                            >
                                <Link
                                    v-if="prevUrl"
                                    :href="prevUrl"
                                    class="flex items-center gap-2 text-sm text-[#607B96] transition-colors hover:text-white"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                    <span>prev_img</span>
                                </Link>
                                <div v-else />

                                <Link
                                    v-if="nextUrl"
                                    :href="nextUrl"
                                    class="flex items-center gap-2 text-sm text-[#607B96] transition-colors hover:text-white"
                                >
                                    <span>next_img</span>
                                    <ChevronRight class="h-4 w-4" />
                                </Link>
                                <div v-else />
                            </div>

                    </div>
                </div>
            </div>
        </main>
    </GuestLayout>
</template>

<style scoped>
.protected-image {
    width: 100%;
    aspect-ratio: var(--aspect-ratio, 1.5);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
    user-select: none;
    -webkit-user-select: none;
    -webkit-touch-callout: none;
}

.protected-image::after {
    content: '';
    position: absolute;
    inset: 0;
    background: transparent;
}

.photo-description {
    color: #607b96;
}

.photo-description :deep(p) {
    margin-bottom: 0.75rem;
}

.photo-description :deep(p:last-child) {
    margin-bottom: 0;
}

.photo-description :deep(a) {
    color: #43d9ad;
    text-decoration: underline;
    text-underline-offset: 2px;
    transition: color 0.2s;
}

.photo-description :deep(a:hover) {
    color: #5fe8bc;
}

.photo-description :deep(strong),
.photo-description :deep(b) {
    color: #fff;
    font-weight: 600;
}

.photo-description :deep(em),
.photo-description :deep(i) {
    font-style: italic;
}

.photo-description :deep(u) {
    text-decoration: underline;
    text-underline-offset: 2px;
}

.photo-description :deep(s),
.photo-description :deep(strike) {
    text-decoration: line-through;
}

.photo-description :deep(h1),
.photo-description :deep(h2),
.photo-description :deep(h3),
.photo-description :deep(h4) {
    color: #fff;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.photo-description :deep(h1:first-child),
.photo-description :deep(h2:first-child),
.photo-description :deep(h3:first-child),
.photo-description :deep(h4:first-child) {
    margin-top: 0;
}

.photo-description :deep(ul),
.photo-description :deep(ol) {
    padding-left: 1.5rem;
    margin-left: 0;
    margin-bottom: 0.75rem;
}

.photo-description :deep(ul) {
    list-style-type: disc;
    list-style-position: outside;
}

.photo-description :deep(ol) {
    list-style-type: decimal;
    list-style-position: outside;
}

.photo-description :deep(li) {
    margin-bottom: 0.25rem;
    padding-left: 0.25rem;
}

.photo-description :deep(li p) {
    margin-bottom: 0;
}

.photo-description :deep(blockquote) {
    border-left: 3px solid #4d5bce;
    padding-left: 1rem;
    margin: 1rem 0;
    color: #8a9bb3;
    font-style: italic;
}

.photo-description :deep(code:not(pre code)) {
    background-color: #1e2d3d;
    color: #43d9ad;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
}

.photo-description :deep(pre) {
    background-color: #011627 !important;
    border: 1px solid #1e2d3d;
    border-radius: 0.5rem;
    padding: 1rem;
    margin: 1rem 0;
    overflow-x: auto;
}

.photo-description :deep(pre code) {
    background: none;
    padding: 0;
    font-size: 0.875rem;
    line-height: 1.6;
}

.photo-description :deep(.shiki) {
    background-color: #011627 !important;
}

.photo-description :deep(hr) {
    border: none;
    border-top: 1px solid #1e2d3d;
    margin: 1.5rem 0;
}

.photo-description :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.photo-description :deep(th),
.photo-description :deep(td) {
    border: 1px solid #1e2d3d;
    padding: 0.5rem 0.75rem;
    text-align: left;
}

.photo-description :deep(th) {
    background-color: #011627;
    color: #fff;
    font-weight: 600;
}

.photo-description :deep(tr:nth-child(even)) {
    background-color: rgba(30, 45, 61, 0.3);
}

.photo-description :deep(mark) {
    background-color: rgba(77, 91, 206, 0.3);
    color: inherit;
    padding: 0.125rem 0.25rem;
    border-radius: 0.125rem;
}

.photo-description :deep(sup) {
    font-size: 0.75em;
    vertical-align: super;
}

.photo-description :deep(sub) {
    font-size: 0.75em;
    vertical-align: sub;
}
</style>
