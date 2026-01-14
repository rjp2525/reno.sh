<script lang="ts" setup>
import { Icon } from '.';
</script>

<template>
    <footer
        class="border-t-navy text-menu flex h-10 min-h-10 border-t text-sm md:justify-between"
    >
        <div class="flex w-full justify-between md:justify-start">
            <span
                class="md:border-r-navy flex h-full items-center justify-center border-r border-r-transparent px-5"
            >
                im on the internet:
            </span>
            <div class="flex">
                <a
                    href="https://x.com/RenoPhilibert"
                    target="_blank"
                    class="group border-r-navy flex h-full w-12 items-center justify-center border-r"
                >
                    <Icon
                        name="logo-x"
                        class="mx-auto h-5 w-5 opacity-60 transition-all duration-200 group-hover:opacity-100"
                    />
                </a>
                <a
                    href="https://www.facebook.com/reno.philibert.77/"
                    target="_blank"
                    class="group border-r-navy flex h-full w-12 items-center justify-center border-r"
                >
                    <Icon
                        name="logo-facebook"
                        class="mx-auto h-5 w-5 opacity-60 transition-all duration-200 group-hover:opacity-100"
                    />
                </a>
                <a
                    href="https://instagram.com/renophilibert"
                    target="_blank"
                    class="group border-r-navy flex h-full w-12 items-center justify-center border-r"
                >
                    <Icon
                        name="logo-instagram"
                        class="mx-auto h-5 w-5 opacity-60 transition-all duration-200 group-hover:opacity-100"
                    />
                </a>
                <a
                    href="https://github.com/rjp2525"
                    target="_blank"
                    class="group border-r-navy flex h-full w-12 items-center justify-center border-r md:hidden"
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
            class="border-l-navy border-opacity-100 hidden items-center border-l px-5 opacity-60 transition-all duration-200 hover:opacity-100 md:flex"
        >
            @rjp2525
            <Icon name="logo-github" class="ml-2 h-5 w-5" />
        </a>
    </footer>
</template>
