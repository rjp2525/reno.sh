<script lang="ts" setup>
import { home } from '@/routes';
</script>

<template>
    <Link
        :href="home.url()"
        class="border-r-navy text-menu hover:bg-navy/75 flex h-full items-center border-r px-6 transition-all duration-200 hover:text-white"
        >reno-philibert</Link
    >
</template>
