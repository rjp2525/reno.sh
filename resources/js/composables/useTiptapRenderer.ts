import { generateHTML } from '@tiptap/html';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { computed, type ComputedRef } from 'vue';

export interface TiptapContent {
    type: string;
    content?: TiptapContent[];
    text?: string;
    attrs?: Record<string, unknown>;
    marks?: Array<{ type: string; attrs?: Record<string, unknown> }>;
}

export interface UseTiptapRendererOptions {
    content: TiptapContent | string | null | undefined;
}

export function useTiptapRenderer(content: () => TiptapContent | string | null | undefined) {
    const extensions = [
        StarterKit,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-cyan-400 hover:text-cyan-300 transition-colors',
            },
        }),
        Image.configure({
            HTMLAttributes: {
                class: 'rounded-lg max-w-full',
            },
        }),
    ];

    const renderedHtml: ComputedRef<string> = computed(() => {
        const rawContent = content();

        if (!rawContent) {
            return '';
        }

        if (typeof rawContent === 'string') {
            return rawContent;
        }

        try {
            return generateHTML(rawContent as TiptapContent, extensions);
        } catch (error) {
            console.error('Error rendering Tiptap content:', error);
            return '';
        }
    });

    const hasContent = computed(() => {
        const rawContent = content();
        if (!rawContent) return false;
        if (typeof rawContent === 'string') return rawContent.trim().length > 0;
        return true;
    });

    return {
        renderedHtml,
        hasContent,
    };
}
