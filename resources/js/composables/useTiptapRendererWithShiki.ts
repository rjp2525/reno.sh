import { generateHTML } from '@tiptap/html';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { codeToHtml, type BundledLanguage } from 'shiki';
import { computed, ref, watch, type Ref } from 'vue';

export interface TiptapContent {
    type: string;
    content?: TiptapContent[];
    text?: string;
    attrs?: Record<string, unknown>;
    marks?: Array<{ type: string; attrs?: Record<string, unknown> }>;
}

const extensions = [
    StarterKit,
    Link.configure({
        openOnClick: false,
        HTMLAttributes: {
            class: 'text-[#43D9AD] hover:text-[#5fe8bc] transition-colors underline underline-offset-2',
        },
    }),
    Image.configure({
        HTMLAttributes: {
            class: 'rounded-lg max-w-full',
        },
    }),
];

async function highlightCodeBlocks(html: string): Promise<string> {
    const codeBlockRegex =
        /<pre><code(?:\s+class="language-(\w+)")?>([\s\S]*?)<\/code><\/pre>/g;

    const matches = [...html.matchAll(codeBlockRegex)];
    if (matches.length === 0) return html;

    let result = html;

    for (const match of matches) {
        const [fullMatch, language, code] = match;
        const decodedCode = code
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&amp;/g, '&')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'");

        try {
            const highlighted = await codeToHtml(decodedCode, {
                lang: (language || 'plaintext') as BundledLanguage,
                theme: 'github-dark',
            });
            result = result.replace(fullMatch, highlighted);
        } catch {
        }
    }

    return result;
}

export function useTiptapRendererWithShiki(
    content: () => TiptapContent | string | null | undefined,
) {
    const renderedHtml: Ref<string> = ref('');
    const isProcessing = ref(false);

    const hasContent = computed(() => {
        const rawContent = content();
        if (!rawContent) return false;
        if (typeof rawContent === 'string') return rawContent.trim().length > 0;
        return true;
    });

    watch(
        content,
        async (rawContent) => {
            if (!rawContent) {
                renderedHtml.value = '';
                return;
            }

            isProcessing.value = true;

            try {
                let html: string;

                if (typeof rawContent === 'string') {
                    html = rawContent;
                } else {
                    html = generateHTML(rawContent as TiptapContent, extensions);
                }

                renderedHtml.value = await highlightCodeBlocks(html);
            } catch (error) {
                console.error('Error rendering Tiptap content:', error);
                renderedHtml.value = '';
            } finally {
                isProcessing.value = false;
            }
        },
        { immediate: true },
    );

    return {
        renderedHtml,
        hasContent,
        isProcessing,
    };
}
