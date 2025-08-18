<script lang="ts" setup>
import { GitHubOctoCat, NavbarLink, NavbarLogo } from '.';
</script>

<template>
    <header class="hidden w-full flex-col lg:flex">
        <nav
            class="flex h-11 w-full justify-between border-b border-b-navy text-sm"
        >
            <GitHubOctoCat url="https://github.com/rjp2525" />
            <div class="flex">
                <NavbarLogo />
                <NavbarLink
                    :url="route('home')"
                    label="_hello"
                    :active="route().current() === 'home'"
                />
                <NavbarLink
                    :url="route('about')"
                    label="_about"
                    :active="route().current() === 'about'"
                />
                <NavbarLink
                    :url="route('projects')"
                    label="_projects"
                    :active="
                        route().current() === 'projects' ||
                        route().current() === 'projects.show'
                    "
                />
                <NavbarLink url="#" label="_photography" :active="false" />
                <NavbarLink url="#" label="_blog" :active="false" />
            </div>
            <NavbarLink
                url="#"
                class="border-l border-r-0 border-l-navy"
                label="_contact-me"
                :active="false"
            />
        </nav>
    </header>
</template>
