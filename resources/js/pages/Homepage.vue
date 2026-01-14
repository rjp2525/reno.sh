<script setup lang="ts">
import { GuestLayout } from '@/layouts';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();
</script>

<template>
    <GuestLayout>
        <Head title="Full Stack Software Engineer" />

        <main
            class="flex min-h-full w-full flex-col px-4 py-4 lg:px-0 lg:pl-72"
        >
            <div class="custom-gradient-blue"></div>
            <div class="custom-gradient-green"></div>
            <div class="my-auto flex w-full flex-col-reverse lg:flex-row">
                <section
                    class="m-0 flex w-full flex-col items-center text-center lg:items-start lg:justify-center lg:text-left"
                >
                    <div class="pb-8 lg:pb-12">
                        <span class="text-lg leading-none"
                            >Howdy there, I'm</span
                        >
                        <h1
                            class="py-4 text-4xl leading-none text-white lg:text-6xl"
                        >
                            Reno Philibert
                        </h1>
                        <span
                            class="diple text-purple flex justify-center gap-2 text-2xl leading-none lg:justify-start lg:text-3xl"
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
                        <span class="hidden lg:block">
                            // building awesome things for the web since 2011
                        </span>
                        <span class="block">
                            // check out my profile on Github:
                        </span>
                        <p class="font-medium break-all lg:break-normal">
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
                    class="z-20 mb-4 flex w-full flex-col items-center lg:mb-0"
                >
                    <div
                        class="relative flex h-auto w-full max-w-[280px] items-center justify-center p-4 lg:max-h-[65vh] lg:min-h-[350px] lg:w-auto lg:max-w-none lg:p-7"
                    >
                        <img
                            src="@img/home-intro-self.png"
                            class="max-h-full w-full rounded object-contain lg:w-auto"
                        />
                    </div>
                </section>
            </div>
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
