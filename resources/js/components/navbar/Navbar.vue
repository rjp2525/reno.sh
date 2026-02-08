<script lang="ts" setup>
import { contact, gallery, home, projects } from '@/routes';
import { professional } from '@/routes/about';
import { index as blogIndex } from '@/routes/blog';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { GitHubOctoCat, MobileNav, NavbarLink, NavbarLogo } from '.';

const page = usePage();
const currentUrl = computed(() => page.url);

const isActive = (path: string, exact = false) => {
    if (exact) {
        return currentUrl.value === path;
    }
    return currentUrl.value.startsWith(path);
};
</script>

<template>
    <MobileNav />

    <header class="hidden w-full flex-col lg:flex">
        <nav
            class="border-b-navy flex h-11 w-full justify-between border-b text-sm"
        >
            <GitHubOctoCat url="https://github.com/rjp2525" />
            <div class="flex">
                <NavbarLogo />
                <NavbarLink
                    :url="home.url()"
                    label="_hello"
                    :active="isActive('/', true)"
                />
                <NavbarLink
                    :url="professional.url()"
                    label="_about"
                    :active="isActive('/about')"
                />
                <NavbarLink
                    :url="projects.url()"
                    label="_projects"
                    :active="isActive('/projects')"
                />
                <NavbarLink
                    :url="gallery.url()"
                    label="_photography"
                    :active="isActive('/gallery')"
                />
                <NavbarLink
                    :url="blogIndex.url()"
                    label="_blog"
                    :active="isActive('/blog')"
                />
            </div>
            <NavbarLink
                :url="contact.url()"
                class="border-l-navy border-r-0 border-l"
                label="_contact-me"
                :active="isActive('/contact')"
            />
        </nav>
    </header>
</template>
