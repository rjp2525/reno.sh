<script setup lang="ts">
import { contact, gallery, home, projects } from '@/routes';
import { professional } from '@/routes/about';
import { index as blogIndex } from '@/routes/blog';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { GitHubOctoCat, MobileNav, NavbarLink, NavbarLogo } from '.';

const page = usePage();
const urls = {
    home: home.url(),
    about: professional.url(),
    projects: projects.url(),
    gallery: gallery.url(),
    blog: blogIndex.url(),
    contact: contact.url(),
};
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
                    :url="urls.home"
                    label="_hello"
                    :active="isActive('/', true)"
                />
                <NavbarLink
                    :url="urls.about"
                    label="_about"
                    :active="isActive('/about')"
                />
                <NavbarLink
                    :url="urls.projects"
                    label="_projects"
                    :active="isActive('/projects')"
                />
                <NavbarLink
                    :url="urls.gallery"
                    label="_photography"
                    :active="isActive('/gallery')"
                />
                <NavbarLink
                    :url="urls.blog"
                    label="_blog"
                    :active="isActive('/blog')"
                />
            </div>
            <NavbarLink
                :url="urls.contact"
                class="border-l-navy border-r-0 border-l"
                label="_contact-me"
                :active="isActive('/contact')"
            />
        </nav>
    </header>
</template>
