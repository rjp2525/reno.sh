<?php

use App\Services\Social\Providers\BlueskyRichText;

/** Slice the facet's byte range back out of the text. */
function facetText(string $text, array $facet): string
{
    return substr($text, $facet['index']['byteStart'], $facet['index']['byteEnd'] - $facet['index']['byteStart']);
}

it('uses UTF-8 byte offsets around emoji and accents', function () {
    $text = 'café 🎉 #photo https://reno.sh/x?a=1.';
    $facets = BlueskyRichText::facets($text);

    expect($facets)->toHaveCount(2)
        // "café " = 6 bytes, "🎉 " = 5 bytes
        ->and($facets[0]['index'])->toBe(['byteStart' => 11, 'byteEnd' => 17])
        ->and($facets[0]['features'][0])->toBe(['$type' => 'app.bsky.richtext.facet#tag', 'tag' => 'photo'])
        ->and($facets[1]['index'])->toBe(['byteStart' => 18, 'byteEnd' => 39])
        ->and($facets[1]['features'][0]['uri'])->toBe('https://reno.sh/x?a=1')
        ->and(facetText($text, $facets[1]))->toBe('https://reno.sh/x?a=1');
});

it('handles ZWJ emoji sequences, CJK and RTL text', function () {
    $text = "👨‍👩‍👧‍👦 家族 #写真 مرحبا #سلام\nhttps://example.com/путь";
    $facets = BlueskyRichText::facets($text);

    expect(array_map(fn ($f) => facetText($text, $f), $facets))
        ->toBe(['#写真', '#سلام', 'https://example.com/путь'])
        ->and($facets[0]['index']['byteStart'])->toBe(strlen('👨‍👩‍👧‍👦 家族 '))
        ->and($facets[0]['features'][0]['tag'])->toBe('写真');
});

it('ignores numeric tags, mid-word hashes and URL fragments', function () {
    $text = 'Issue #123 C# a#b see https://reno.sh/page#section and #ok_tag!';
    $facets = BlueskyRichText::facets($text);

    expect(array_map(fn ($f) => facetText($text, $f), $facets))
        ->toBe(['https://reno.sh/page#section', '#ok_tag']);
});

it('strips trailing punctuation and unbalanced parens from links', function () {
    $text = '(see https://en.wikipedia.org/wiki/Bokeh_(photography)) and https://reno.sh, ok';
    $links = array_map(fn ($f) => $f['features'][0]['uri'], BlueskyRichText::facets($text));

    expect($links)->toBe(['https://en.wikipedia.org/wiki/Bokeh_(photography)', 'https://reno.sh'])
        ->and(BlueskyRichText::firstLink($text))->toBe('https://en.wikipedia.org/wiki/Bokeh_(photography)');
});

it('returns no facets for plain text', function () {
    expect(BlueskyRichText::facets('just words, nothing else'))->toBe([]);
});
