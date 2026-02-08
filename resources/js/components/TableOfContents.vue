<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    entries: TocEntry[];
}>();

const activeId = ref('');

let observer: IntersectionObserver | null = null;

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    activeId.value = entry.target.id;
                }
            }
        },
        {
            rootMargin: '-80px 0px -60% 0px',
            threshold: 0.1,
        },
    );

    props.entries.forEach((tocEntry) => {
        const el = document.getElementById(tocEntry.id);
        if (el) observer?.observe(el);
    });
});

onBeforeUnmount(() => {
    observer?.disconnect();
});

const scrollTo = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const getIndent = (level: string) => {
    switch (level) {
        case 'h3':
            return 'pl-4';
        case 'h4':
            return 'pl-8';
        default:
            return '';
    }
};
</script>

<template>
    <nav v-if="entries.length > 0" class="sticky top-6">
        <h4
            class="mb-3 text-xs font-semibold tracking-wider text-[#607B96] uppercase"
        >
            On this page
        </h4>
        <ul class="space-y-1">
            <li
                v-for="entry in entries"
                :key="entry.id"
                :class="getIndent(entry.level)"
            >
                <button
                    class="block w-full border-l-2 py-1 pr-2 pl-3 text-left text-sm transition-colors"
                    :class="
                        activeId === entry.id
                            ? 'border-[#FEA55F] text-white'
                            : 'border-transparent text-[#607B96] hover:border-[#607B96]/50 hover:text-white'
                    "
                    @click="scrollTo(entry.id)"
                >
                    {{ entry.title }}
                </button>
            </li>
        </ul>
    </nav>
</template>
