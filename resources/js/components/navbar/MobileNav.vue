<script lang="ts" setup>
import { home, projects } from '@/routes';
import { professional } from '@/routes/about';
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const isOpen = ref(false);
const headerRef = ref<HTMLElement | null>(null);

const page = usePage();
const currentUrl = computed(() => page.url);

const toggleMenu = () => {
    isOpen.value = !isOpen.value;
};

const closeMenu = () => {
    isOpen.value = false;
};

const handleClickOutside = (event: MouseEvent) => {
    if (
        headerRef.value &&
        !headerRef.value.contains(event.target as Node) &&
        isOpen.value
    ) {
        closeMenu();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});

const navLinks = [
    { label: '_hello', url: home.url(), matchPath: '/', exact: true },
    {
        label: '_about',
        url: professional.url(),
        matchPath: '/about',
        exact: false,
    },
    {
        label: '_projects',
        url: projects.url(),
        matchPath: '/projects',
        exact: false,
    },
    { label: '_photography', url: null, matchPath: null, exact: false },
    { label: '_blog', url: null, matchPath: null, exact: false },
    { label: '_contact-me', url: null, matchPath: null, exact: false },
];

const isLinkActive = (link: (typeof navLinks)[number]) => {
    if (!link.matchPath) return false;
    if (link.exact) {
        return currentUrl.value === link.matchPath;
    }
    return currentUrl.value.startsWith(link.matchPath);
};
</script>

<template>
    <header ref="headerRef" class="relative flex w-full flex-col lg:hidden">
        <nav
            class="border-b-navy flex h-14 w-full items-center justify-between border-b px-4"
        >
            <Link
                :href="home.url()"
                class="text-menu transition-all duration-200 hover:text-white"
            >
                reno-philibert
            </Link>

            <button
                @click="toggleMenu"
                class="text-menu relative z-50 flex h-10 w-10 flex-col items-center justify-center gap-1.5 transition-colors hover:text-white"
                :aria-expanded="isOpen"
                aria-label="Toggle navigation menu"
            >
                <span
                    class="h-0.5 w-5 bg-current transition-all duration-300"
                    :class="{
                        'translate-y-2 rotate-45': isOpen,
                    }"
                ></span>
                <span
                    class="h-0.5 w-5 bg-current transition-all duration-300"
                    :class="{ 'opacity-0': isOpen }"
                ></span>
                <span
                    class="h-0.5 w-5 bg-current transition-all duration-300"
                    :class="{
                        '-translate-y-2 -rotate-45': isOpen,
                    }"
                ></span>
            </button>
        </nav>

        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-4"
        >
            <div
                v-if="isOpen"
                class="border-b-navy bg-background absolute top-14 right-0 left-0 z-40 border-b"
            >
                <div class="flex flex-col">
                    <Link
                        v-for="link in navLinks"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        @click="closeMenu"
                        class="border-b-navy text-menu hover:bg-navy/50 border-b px-4 py-4 transition-all duration-200 hover:text-white"
                        :class="{
                            'border-l-2 border-l-orange-400 !text-white':
                                isLinkActive(link),
                        }"
                    >
                        {{ link.label }}
                    </Link>
                </div>
            </div>
        </Transition>
    </header>
</template>
