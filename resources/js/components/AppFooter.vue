<script lang="ts" setup>
import { Icon } from '.';
</script>

<template>
    <footer
        class="flex h-10 min-h-10 border-t border-t-navy text-sm text-menu md:justify-between"
    >
        <div class="flex w-full justify-between md:justify-start">
            <span
                class="flex h-full items-center justify-center border-r border-r-navy px-5"
            >
                find me on:
            </span>
            <div class="flex">
                <a
                    href="https://x.com/RenoPhilibert"
                    target="_blank"
                    class="group flex h-full w-12 items-center justify-center border-r border-r-navy"
                >
                    <Icon
                        name="logo-x"
                        class="mx-auto h-5 w-5 opacity-60 transition-all duration-200 group-hover:opacity-100"
                    />
                </a>
                <a
                    href="https://www.facebook.com/reno.philibert.77/"
                    target="_blank"
                    class="group flex h-full w-12 items-center justify-center border-r border-r-navy"
                >
                    <Icon
                        name="logo-facebook"
                        class="mx-auto h-5 w-5 opacity-60 transition-all duration-200 group-hover:opacity-100"
                    />
                </a>
                <a
                    href="https://instagram.com/renophilibert"
                    target="_blank"
                    class="group flex h-full w-12 items-center justify-center border-r border-r-navy"
                >
                    <Icon
                        name="logo-instagram"
                        class="mx-auto h-5 w-5 opacity-60 transition-all duration-200 group-hover:opacity-100"
                    />
                </a>
                <a
                    href="https://github.com/rjp2525"
                    target="_blank"
                    class="group flex h-full w-12 items-center justify-center border-r border-r-navy md:hidden"
                >
                    <Icon
                        name="logo-github"
                        class="mx-auto h-5 w-5 opacity-60 transition-all duration-200 group-hover:opacity-100"
                    />
                </a>
            </div>
        </div>

        <a
            href="https://github.com/rjp2525"
            target="_blank"
            class="hidden items-center border-l border-l-navy border-opacity-100 px-5 opacity-60 transition-all duration-200 hover:opacity-100 md:flex"
        >
            @rjp2525
            <Icon name="logo-github" class="ml-2 h-5 w-5" />
        </a>
    </footer>
</template>
