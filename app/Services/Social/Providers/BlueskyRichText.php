<?php

declare(strict_types=1);

namespace App\Services\Social\Providers;

/**
 * Builds app.bsky.richtext.facet entries for links and hashtags.
 *
 * Facet indexes are UTF-8 *byte* offsets (not characters or graphemes);
 * preg's PREG_OFFSET_CAPTURE reports byte offsets, which is exactly that.
 */
final class BlueskyRichText
{
    private const string LINK_PATTERN = '~https?://[^\s<>"]+~iu';

    // A tag starts after whitespace/start-of-text, has at least one non-digit and stops at whitespace/punctuation.
    private const string TAG_PATTERN = '/(?<![^\s])[#＃]([\p{L}\p{M}\p{N}_]*[\p{L}\p{M}_][\p{L}\p{M}\p{N}_]*)/u';

    /** @return list<array{index: array{byteStart: int, byteEnd: int}, features: list<array<string, string>>}> */
    public static function facets(string $text): array
    {
        $facets = [];
        $linkRanges = [];

        preg_match_all(self::LINK_PATTERN, $text, $links, PREG_OFFSET_CAPTURE);

        foreach ($links[0] as [$url, $start]) {
            $url = self::trimTrailingPunctuation($url);
            $end = $start + strlen($url);
            $linkRanges[] = [$start, $end];

            $facets[] = [
                'index' => ['byteStart' => $start, 'byteEnd' => $end],
                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $url]],
            ];
        }

        preg_match_all(self::TAG_PATTERN, $text, $tags, PREG_OFFSET_CAPTURE);

        foreach ($tags[0] as $i => [$match, $start]) {
            $tag = $tags[1][$i][0];

            if (mb_strlen($tag) > 64) {
                continue;
            }

            $end = $start + strlen($match);

            foreach ($linkRanges as [$ls, $le]) {
                if ($start < $le && $end > $ls) {
                    continue 2;
                }
            }

            $facets[] = [
                'index' => ['byteStart' => $start, 'byteEnd' => $end],
                'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => $tag]],
            ];
        }

        usort($facets, fn ($a, $b) => $a['index']['byteStart'] <=> $b['index']['byteStart']);

        return $facets;
    }

    /** The first link in the text, used for the external card when there are no images. */
    public static function firstLink(string $text): ?string
    {
        return preg_match(self::LINK_PATTERN, $text, $m) ? self::trimTrailingPunctuation($m[0]) : null;
    }

    private static function trimTrailingPunctuation(string $url): string
    {
        $url = rtrim($url, '.,;:!?\'"');

        // Drop a trailing ")" unless the URL itself opened one, e.g. wiki links.
        while (str_ends_with($url, ')') && substr_count($url, '(') < substr_count($url, ')')) {
            $url = rtrim(substr($url, 0, -1), '.,;:!?\'"');
        }

        return $url;
    }
}
