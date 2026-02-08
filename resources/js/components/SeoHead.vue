<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string | null;
        image?: string | null;
        url?: string | null;
        type?: string;
        keywords?: string | null;
        noindex?: boolean;
    }>(),
    {
        type: 'website',
    },
);

const page = usePage();
const appUrl = computed(() => page.props.appUrl as string);
const canonicalUrl = computed(() => props.url ?? `${appUrl.value}${page.url}`);
const siteName = 'Reno Philibert';

const absoluteImage = computed(() => {
    const img = props.image || '/images/og-default.png';
    if (img.startsWith('http')) return img;
    return `${appUrl.value}${img.startsWith('/') ? '' : '/'}${img}`;
});
</script>

<template>
    <Head :title="title">
        <meta v-if="description" head-key="description" name="description" :content="description" />
        <meta v-if="keywords" head-key="keywords" name="keywords" :content="keywords" />
        <meta v-if="noindex" head-key="robots" name="robots" content="noindex, nofollow" />

        <meta head-key="og:title" property="og:title" :content="title" />
        <meta v-if="description" head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:image" property="og:image" :content="absoluteImage" />
        <meta head-key="og:type" property="og:type" :content="type" />
        <meta head-key="og:url" property="og:url" :content="canonicalUrl" />
        <meta head-key="og:site_name" property="og:site_name" :content="siteName" />

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <meta v-if="description" head-key="twitter:description" name="twitter:description" :content="description" />
        <meta head-key="twitter:image" name="twitter:image" :content="absoluteImage" />

        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
    </Head>
</template>
