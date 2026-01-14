import { codeToHtml, type BundledLanguage, type BundledTheme } from 'shiki';
import { ref, type Ref } from 'vue';

export interface UseCodeHighlighterOptions {
    theme?: BundledTheme;
    defaultLanguage?: BundledLanguage;
}

export function useCodeHighlighter(options: UseCodeHighlighterOptions = {}) {
    const { theme = 'github-dark', defaultLanguage = 'typescript' } = options;

    const isLoading = ref(false);

    async function highlightCode(
        code: string,
        lang: BundledLanguage = defaultLanguage,
    ): Promise<string> {
        isLoading.value = true;

        try {
            const html = await codeToHtml(code, {
                lang,
                theme,
            });
            return html;
        } finally {
            isLoading.value = false;
        }
    }

    return {
        highlightCode,
        isLoading,
    };
}
