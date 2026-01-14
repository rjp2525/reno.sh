<script setup lang="ts">
import { GuestLayout } from '@/layouts';
import { inject, ref } from 'vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();

const isMobile = inject('isMobile', ref(false));
</script>

<template>
    <GuestLayout>
        <Head title="Full Stack Software Engineer" />

        <main
            class="my-8 flex h-full w-full flex-auto overflow-hidden lg:pl-72"
        >
            <div class="custom-gradient-blue"></div>
            <div class="custom-gradient-green"></div>
            <section class="m-0 flex w-full flex-col justify-center">
                <div class="pb-12">
                    <span class="text-lg leading-none">Howdy there, I'm</span>
                    <h1 class="py-4 text-6xl leading-none text-white">
                        Reno Philibert
                    </h1>
                    <span
                        class="diple text-purple flex gap-2 text-3xl leading-none"
                    >
                        >
                        <h2
                            class="animate-typewriter w-fit max-w-fit overflow-hidden border-r-[3px] border-r-white/75 pr-[2px] whitespace-nowrap"
                        >
                            Full Stack Engineer
                        </h2>
                    </span>
                </div>
                <div
                    class="text-menu flex flex-col space-y-3 text-sm leading-none"
                >
                    <span :class="{ hide: isMobile }">
                        // building awesome things for the web since 2011
                    </span>
                    <span :class="{ hide: !isMobile }">
                        // check out my profile on Github:
                    </span>
                    <p class="font-medium">
                        <span class="text-purple"> const </span>
                        <span class="text-github-text"> githubLink </span>
                        <span class="text-white"> = </span>
                        <a
                            class="leading-normal text-red-400 underline underline-offset-4 transition-all duration-200 hover:text-red-500 focus:text-red-500 active:text-red-500"
                            href="https://github.com/rjp2525"
                            target="_blank"
                        >
                            https://github.com/rjp2525
                        </a>
                    </p>
                </div>
            </section>
            <section
                data-aos="fade-up"
                class="z-20 flex h-full w-full flex-col justify-center"
                v-if="!isMobile"
            >
                <div
                    class="relative flex h-[475px] w-[530px] items-center justify-between p-7"
                >
                    <img src="@img/home-intro-self.png" class="rounded" />
                </div>
            </section>
        </main>
    </GuestLayout>
</template>

<style lang="scss" scoped>
.custom-gradient-blue {
    @apply fixed right-[5%] bottom-1/4 z-10 h-[300px] w-[300px] opacity-50;
    border-radius: 0% 0% 50% 50%;
    filter: blur(70px);
    background: radial-gradient(
        circle at 50% 50%,
        rgba(77, 91, 206, 1),
        rgba(76, 0, 255, 0)
    );
}

.custom-gradient-green {
    @apply absolute top-[20%] right-[30%] z-10 h-[300px] w-[300px] opacity-50;
    border-radius: 0% 50% 0% 50%;
    filter: blur(70px);
    background: radial-gradient(
        circle at 50% 50%,
        rgba(67, 217, 173, 1),
        rgba(76, 0, 255, 0)
    );
}
</style>
