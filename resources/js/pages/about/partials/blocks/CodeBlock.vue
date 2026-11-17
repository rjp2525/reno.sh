<script setup lang="ts">
import { Check, Clipboard } from 'lucide-vue-next';
import { codeToHtml, type BundledLanguage } from 'shiki';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    data: {
        language: string;
        code: string;
        filename?: string | null;
    };
}>();

const highlightedHtml = ref('');
const isLoading = ref(true);
const copied = ref(false);

const languageLabel = (props.data.language || 'plaintext').toUpperCase();

const copyCode = async () => {
    await navigator.clipboard.writeText(props.data.code);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

onMounted(async () => {
    try {
        highlightedHtml.value = await codeToHtml(props.data.code, {
            lang: (props.data.language || 'plaintext') as BundledLanguage,
            theme: 'github-dark',
        });
    } catch {
        highlightedHtml.value = '';
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#1E2D3D]">
        <div
            class="flex items-center justify-between border-b border-[#1E2D3D] bg-[#0d1b2a] px-4 py-2"
        >
            <span v-if="data.filename" class="text-xs text-[#607B96]">{{ data.filename }}</span>
            <span v-else class="text-xs text-[#607B96]/60">{{ languageLabel }}</span>
            <div class="flex items-center gap-2">
                <span v-if="data.filename" class="text-xs text-[#607B96]/60">{{ languageLabel }}</span>
                <button
                    class="cursor-pointer rounded p-1 text-[#607B96] transition-colors hover:bg-[#1E2D3D] hover:text-white"
                    @click="copyCode"
                >
                    <Check v-if="copied" class="h-4 w-4 text-[#43D9AD]" />
                    <Clipboard v-else class="h-4 w-4" />
                </button>
            </div>
        </div>
        <!-- Plain code until Shiki loads (and in the SSR HTML, so the code is always crawlable). -->
        <div
            v-if="!isLoading && highlightedHtml"
            class="code-block-content overflow-x-auto text-sm"
            v-html="highlightedHtml"
        />
        <pre v-else class="code-block-fallback overflow-x-auto bg-[#24292e] p-4 text-sm text-[#e1e4e8]"><code>{{ data.code }}</code></pre>
    </div>
</template>

<style scoped>
.code-block-content :deep(pre) {
    margin: 0;
    padding: 1rem;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(96, 123, 150, 0.4) transparent;
}

.code-block-content :deep(pre)::-webkit-scrollbar {
    height: 8px;
}

.code-block-content :deep(pre)::-webkit-scrollbar-track {
    background: transparent;
}

.code-block-content :deep(pre)::-webkit-scrollbar-thumb {
    background: rgba(96, 123, 150, 0.4);
    border-radius: 4px;
}

.code-block-content :deep(pre)::-webkit-scrollbar-thumb:hover {
    background: rgba(96, 123, 150, 0.6);
}

.code-block-content :deep(code) {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
</style>
