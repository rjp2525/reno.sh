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
                <NavbarLink url="#" label="_hello" :active="true" />
                <NavbarLink url="#" label="_about" :active="false" />
                <NavbarLink url="#" label="_projects" :active="false" />
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
