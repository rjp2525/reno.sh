<script lang="ts" setup>
defineProps<{
    url: string;
    label: string;
    active: boolean;
}>();
</script>

<template>
    <Link
        :href="url"
        class="flex h-full items-center border-r border-r-navy px-6 text-menu transition-all duration-200 hover:bg-navy/75 hover:text-white"
        :class="{ 'border-b-2 border-b-orange-400': active }"
    >
        {{ label }}
    </Link>
</template>
