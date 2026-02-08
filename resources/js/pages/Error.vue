<script setup lang="ts">
import { SeoHead } from '@/components';
import { GuestLayout } from '@/layouts';
import { home } from '@/routes';

const props = defineProps<{
    status: number;
}>();

const meta: Record<number, { title: string; heading: string; description: string }> = {
    403: {
        title: '403 - Forbidden',
        heading: '403',
        description: "you don't have permission to access this resource.",
    },
    404: {
        title: '404 - Not Found',
        heading: '404',
        description: "the page you're looking for doesn't exist or has been moved.",
    },
    500: {
        title: '500 - Server Error',
        heading: '500',
        description: 'something went wrong on our end. please try again later.',
    },
    503: {
        title: '503 - Service Unavailable',
        heading: '503',
        description: 'the site is temporarily down for maintenance. check back soon.',
    },
};

const info = meta[props.status] ?? {
    title: `${props.status} - Error`,
    heading: String(props.status),
    description: 'an unexpected error occurred.',
};
</script>

<template>
    <GuestLayout>
        <SeoHead :title="info.title" noindex />

        <main
            class="flex min-h-full w-full flex-col items-center justify-center px-4 py-4"
        >
            <div class="custom-gradient-blue"></div>
            <div class="custom-gradient-green"></div>

            <div class="z-20 text-center">
                <p class="text-menu mb-4 text-sm">// error</p>
                <h1 class="text-purple mb-6 text-8xl font-bold lg:text-9xl">
                    {{ info.heading }}
                </h1>
                <p class="text-menu mb-10 max-w-md text-sm">
                    {{ info.description }}
                </p>

                <div class="space-y-3 text-sm">
                    <p>
                        <span class="text-purple">const </span>
                        <span class="text-github-text">goHome</span>
                        <span class="text-white"> = </span>
                        <Link
                            :href="home.url()"
                            class="text-red-400 underline underline-offset-4 transition-colors hover:text-red-500"
                        >
                            "take-me-home"
                        </Link>
                        <span class="text-white">;</span>
                    </p>
                </div>
            </div>
        </main>
    </GuestLayout>
</template>

<style scoped>
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
