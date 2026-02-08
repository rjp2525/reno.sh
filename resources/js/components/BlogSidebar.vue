<script setup lang="ts">
import { Icon } from '@/components';
import { computed } from 'vue';

const toKebab = (str: string) => str.toLowerCase().replace(/\s+/g, '-');

const props = defineProps<{
    categories: BlogCategory[];
    tags: BlogTag[];
    selectedCategory: string;
    selectedTags: string[];
    totalPosts: number;
}>();

const emit = defineEmits<{
    selectCategory: [slug: string];
    toggleTag: [slug: string];
}>();

const categoriesWithAll = computed(() => [
    {
        id: 'all',
        name: 'all-posts',
        slug: '',
        color: null,
        post_count: props.totalPosts,
    },
    ...props.categories,
]);
</script>

<template>
    <div
        class="hidden h-full w-full flex-col border-r border-[#1E2D3D] lg:flex lg:max-w-[275px] lg:min-w-[275px] 2xl:max-w-[310px] 2xl:min-w-[310px]"
    >
        <div class="border-b border-[#1E2D3D]">
            <div
                class="flex min-h-7 w-full items-center border-b border-[#1E2D3D] px-4 py-3 text-sm text-white"
            >
                <Icon name="caret-down" class="mr-4 h-3 w-3" />
                <p>categories</p>
            </div>
            <div class="py-2">
                <div
                    v-for="category in categoriesWithAll"
                    :key="category.id"
                    class="mx-4 my-1 flex cursor-pointer items-center py-1 text-[#607B96] transition-all duration-200 hover:text-white"
                    :class="{
                        'text-white': selectedCategory === category.slug,
                    }"
                    @click="emit('selectCategory', category.slug)"
                >
                    <Icon
                        :name="
                            selectedCategory === category.slug
                                ? 'folder-open'
                                : 'folder'
                        "
                        class="mr-3 h-4 w-4 shrink-0"
                        :style="category.color ? { color: category.color } : {}"
                    />
                    <span class="truncate">{{ toKebab(category.name) }}</span>
                    <span
                        v-if="category.post_count"
                        class="ml-auto pl-2 text-xs opacity-60"
                    >
                        {{ category.post_count }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="tags.length > 0">
            <div
                class="flex min-h-7 w-full items-center border-b border-[#1E2D3D] px-4 py-3 text-sm text-white"
            >
                <Icon name="caret-down" class="mr-4 h-3 w-3" />
                <p>tags</p>
            </div>
            <div class="flex flex-wrap gap-2 p-4">
                <button
                    v-for="tag in tags"
                    :key="tag.id"
                    class="rounded-full border px-3 py-1 text-xs transition-colors"
                    :class="
                        selectedTags.includes(tag.slug)
                            ? 'border-[#FEA55F] bg-[#FEA55F]/20 text-[#FEA55F]'
                            : 'border-[#1E2D3D] bg-[#011221] text-[#607B96] hover:border-[#607B96] hover:text-white'
                    "
                    @click="emit('toggleTag', tag.slug)"
                >
                    #{{ tag.name }}
                </button>
            </div>
        </div>
    </div>
</template>
