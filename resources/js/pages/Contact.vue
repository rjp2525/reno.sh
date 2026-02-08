<script setup lang="ts">
import { SeoHead } from '@/components';
import { MobilePageHeader } from '@/components/page';
import { ScrollArea } from '@/components/ui/scroll-area';
import { GuestLayout } from '@/layouts';
import { send as contactSend } from '@/routes/contact';
import { useForm, usePage } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import { computed, inject, ref } from 'vue';

const isMobile = inject('isMobile', ref(false));
const page = usePage();
const flash = computed(
    () => page.props.flash as { success?: string; error?: string } | undefined,
);
const successMessage = computed(() => flash.value?.success);
const errorMessage = computed(() => flash.value?.error);

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
});

const submit = () => {
    form.post(contactSend.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <GuestLayout>
        <SeoHead
            title="Contact Me"
            description="Get in touch with Reno Philibert for project inquiries, collaborations, or just to say hello."
        />

        <main class="flex h-full w-full flex-auto flex-col overflow-hidden">
            <MobilePageHeader>_contact-me</MobilePageHeader>

            <ScrollArea class="h-full w-full">
                <div class="grid h-full min-h-full grid-cols-1 lg:grid-cols-5">
                    <div
                        class="border-b-navy lg:border-r-navy flex flex-col border-b p-6 lg:col-span-2 lg:border-r lg:border-b-0 lg:p-10"
                    >
                        <div class="lg:sticky lg:top-10">
                            <h1 class="mb-1 text-3xl font-bold text-white">
                                _contact-me
                            </h1>
                            <p class="text-menu mb-8 text-sm lowercase">
                                // fill out the form and i'll get back to you
                            </p>

                            <div class="space-y-6">
                                <div>
                                    <span class="text-menu text-xs lowercase"
                                        >// find me on</span
                                    >
                                    <div class="mt-3 space-y-3">
                                        <a
                                            href="https://github.com/rjp2525"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-menu flex items-center gap-3 text-sm transition-colors hover:text-white"
                                        >
                                            <svg
                                                class="h-4 w-4 shrink-0"
                                                fill="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795 .945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12Z"
                                                />
                                            </svg>
                                            rjp2525
                                        </a>
                                        <a
                                            href="https://x.com/RenoPhilibert"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-menu flex items-center gap-3 text-sm transition-colors hover:text-white"
                                        >
                                            <svg
                                                class="h-4 w-4 shrink-0"
                                                fill="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"
                                                />
                                            </svg>
                                            @RenoPhilibert
                                        </a>
                                        <a
                                            href="https://instagram.com/renophilibert"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-menu flex items-center gap-3 text-sm lowercase transition-colors hover:text-white"
                                        >
                                            <svg
                                                class="h-4 w-4 shrink-0"
                                                fill="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"
                                                />
                                            </svg>
                                            renophilibert
                                        </a>
                                    </div>
                                </div>

                                <div class="hidden lg:block">
                                    <span class="text-menu text-xs lowercase"
                                        >// or email me directly</span
                                    >
                                    <div class="mt-3">
                                        <a
                                            href="mailto:hello@reno.sh"
                                            class="text-menu flex items-center gap-3 text-sm transition-colors hover:text-white"
                                        >
                                            <Mail class="h-4 w-4 shrink-0" />
                                            hello@reno.sh
                                        </a>
                                    </div>
                                </div>

                                <div class="hidden pt-8 lg:block">
                                    <div class="space-y-1 text-sm">
                                        <p>
                                            <span class="text-purple"
                                                >const</span
                                            >
                                            <span class="text-github-text">
                                                contact</span
                                            >
                                            <span class="text-white"> = </span>
                                            <span class="text-white">{</span>
                                        </p>
                                        <p class="pl-4">
                                            <span class="text-white"
                                                >name:</span
                                            >
                                            <span class="text-red-400">
                                                "Reno Philibert"</span
                                            ><span class="text-white">,</span>
                                        </p>
                                        <p class="pl-4">
                                            <span class="text-white"
                                                >email:</span
                                            >
                                            <span class="text-red-400">
                                                "hello@reno.sh"</span
                                            ><span class="text-white">,</span>
                                        </p>
                                        <p class="pl-4">
                                            <span class="text-white"
                                                >open_to:</span
                                            >
                                            <span class="text-red-400">
                                                "everything"</span
                                            ><span class="text-white">,</span>
                                        </p>
                                        <p>
                                            <span class="text-white">};</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col p-6 lg:col-span-3 lg:p-10">
                        <Transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-2"
                        >
                            <div
                                v-if="successMessage"
                                class="mb-6 rounded-lg border border-green-800 bg-green-900/30 p-4 text-sm text-green-400"
                            >
                                {{ successMessage }}
                            </div>
                        </Transition>

                        <Transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-2"
                        >
                            <div
                                v-if="errorMessage"
                                class="mb-6 rounded-lg border border-red-800 bg-red-900/30 p-4 text-sm text-red-400"
                            >
                                {{ errorMessage }}
                            </div>
                        </Transition>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label
                                    for="name"
                                    class="text-menu mb-2 flex items-center gap-2 text-sm"
                                >
                                    _name
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="John Doe"
                                    class="w-full rounded-lg border border-[#1E2D3D] bg-[#011627] px-4 py-3 text-sm text-white placeholder-[#607B96] transition-colors focus:border-[#607B96] focus:outline-none"
                                    :class="{
                                        'border-red-500': form.errors.name,
                                    }"
                                />
                                <p
                                    v-if="form.errors.name"
                                    class="mt-1.5 text-xs text-red-400"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="email"
                                    class="text-menu mb-2 flex items-center gap-2 text-sm"
                                >
                                    _email
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="john@example.com"
                                    class="w-full rounded-lg border border-[#1E2D3D] bg-[#011627] px-4 py-3 text-sm text-white placeholder-[#607B96] transition-colors focus:border-[#607B96] focus:outline-none"
                                    :class="{
                                        'border-red-500': form.errors.email,
                                    }"
                                />
                                <p
                                    v-if="form.errors.email"
                                    class="mt-1.5 text-xs text-red-400"
                                >
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="subject"
                                    class="text-menu mb-2 flex items-center gap-2 text-sm"
                                >
                                    _subject
                                </label>
                                <input
                                    id="subject"
                                    v-model="form.subject"
                                    type="text"
                                    placeholder="Project inquiry, collaboration, etc."
                                    class="w-full rounded-lg border border-[#1E2D3D] bg-[#011627] px-4 py-3 text-sm text-white placeholder-[#607B96] transition-colors focus:border-[#607B96] focus:outline-none"
                                    :class="{
                                        'border-red-500': form.errors.subject,
                                    }"
                                />
                                <p
                                    v-if="form.errors.subject"
                                    class="mt-1.5 text-xs text-red-400"
                                >
                                    {{ form.errors.subject }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="message"
                                    class="text-menu mb-2 flex items-center gap-2 text-sm"
                                >
                                    _message
                                </label>
                                <textarea
                                    id="message"
                                    v-model="form.message"
                                    rows="6"
                                    placeholder="Tell me about your project, idea, or just say hello..."
                                    class="w-full resize-none rounded-lg border border-[#1E2D3D] bg-[#011627] px-4 py-3 text-sm text-white placeholder-[#607B96] transition-colors focus:border-[#607B96] focus:outline-none"
                                    :class="{
                                        'border-red-500': form.errors.message,
                                    }"
                                ></textarea>
                                <div
                                    class="mt-1.5 flex items-center justify-between"
                                >
                                    <p
                                        v-if="form.errors.message"
                                        class="text-xs text-red-400"
                                    >
                                        {{ form.errors.message }}
                                    </p>
                                    <span
                                        class="ml-auto text-xs"
                                        :class="
                                            form.message.length > 5000
                                                ? 'text-red-400'
                                                : 'text-[#607B96]'
                                        "
                                    >
                                        {{ form.message.length }} / 5000
                                    </span>
                                </div>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="mx-auto flex items-center gap-2 rounded-lg border border-[#1E2D3D] bg-[#1C2B3A] px-6 py-3 text-sm text-white transition-all duration-200 hover:border-[#607B96] hover:bg-[#263B50] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span v-if="form.processing">sending...</span>
                                <span v-else>submit-message</span>
                            </button>
                        </form>
                    </div>
                </div>
            </ScrollArea>
        </main>
    </GuestLayout>
</template>
