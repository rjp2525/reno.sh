export type HtmlSegment =
    | { kind: 'html'; html: string }
    | { kind: 'code'; language: string; code: string };

const entities: Record<string, string> = {
    '&lt;': '<',
    '&gt;': '>',
    '&amp;': '&',
    '&quot;': '"',
    '&#039;': "'",
    '&#39;': "'",
    '&nbsp;': ' ',
};

/** Decode the few entities Markdown/rich-text renderers emit inside <code>. Works without a DOM (SSR). */
export function decodeEntities(text: string): string {
    return text
        .replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
        .replace(/&#(\d+);/g, (m, dec) => (m in entities ? entities[m] : String.fromCodePoint(Number(dec))))
        .replace(/&(lt|gt|amp|quot|nbsp);/g, (m) => entities[m]);
}

const preCode = /<pre[^>]*>\s*<code(?:\s+class="([^"]*)")?[^>]*>([\s\S]*?)<\/code>\s*<\/pre>/gi;

/**
 * Split rendered HTML into prose and fenced code blocks, so code can be
 * highlighted by the CodeBlock component. ```go fences arrive as class="language-go".
 */
export function splitCodeBlocks(html: string | null | undefined): HtmlSegment[] {
    const source = html ?? '';
    const segments: HtmlSegment[] = [];
    let last = 0;

    for (const match of source.matchAll(preCode)) {
        const before = source.slice(last, match.index);
        if (before.trim()) segments.push({ kind: 'html', html: before });

        const language = /(?:^|\s)language-([\w+#.-]+)/.exec(match[1] ?? '')?.[1] ?? 'plaintext';
        segments.push({ kind: 'code', language, code: decodeEntities(match[2]).replace(/\n$/, '') });
        last = match.index + match[0].length;
    }

    const rest = source.slice(last);
    if (rest.trim() || segments.length === 0) segments.push({ kind: 'html', html: rest });

    return segments;
}
