<?php

use App\Terminal\Ansi;
use App\Terminal\Banner;
use App\Terminal\BlockRenderer;
use App\Terminal\ColorMode;
use App\Terminal\HtmlConverter;
use App\Terminal\Text;

function plain(): HtmlConverter
{
    return new HtmlConverter(new Ansi(ColorMode::None));
}

function widest(string $text): int
{
    return max(array_map(Text::width(...), explode("\n", $text)));
}

describe('Text', function () {
    it('measures ignoring escapes and counting wide characters', function () {
        expect(Text::width("\e[1;38;2;67;217;173mhello\e[0m"))->toBe(5)
            ->and(Text::width('日本語'))->toBe(6)
            ->and(Text::width('café'))->toBe(4);
    });

    it('wraps words without exceeding the width', function () {
        $text = str_repeat('lorem ipsum dolor sit amet ', 20);

        foreach ([10, 23, 40, 80] as $width) {
            expect(widest(Text::wrap($text, $width)))->toBeLessThanOrEqual($width);
        }
    });

    it('hard breaks words longer than the line', function () {
        $wrapped = Text::wrap('see https://example.com/a/really/long/url/that/never/ends/at/all ok', 20);

        expect(widest($wrapped))->toBeLessThanOrEqual(20)
            ->and(str_replace("\n", '', $wrapped))->toContain('https://example.com/a/really/long/url/that/never/ends/at/all');
    });

    it('wraps multibyte and wide text', function () {
        $wrapped = Text::wrap(str_repeat('日本語のテキスト ', 10).str_repeat('ünïcödé ', 10), 15);

        expect(widest($wrapped))->toBeLessThanOrEqual(15);
    });

    it('closes and reopens styles across line breaks', function () {
        $ansi = new Ansi(ColorMode::TrueColor);
        $wrapped = Text::wrap($ansi->bold('one two three four five six'), 10, '  ');
        $lines = explode("\n", $wrapped);

        expect(count($lines))->toBeGreaterThan(1);
        foreach ($lines as $i => $line) {
            expect($line)->toEndWith("\e[0m");
            if ($i > 0) {
                // the indent stays unstyled; the style is reopened after it
                expect($line)->toStartWith("  \e[1m");
            }
        }
        expect(widest($wrapped))->toBeLessThanOrEqual(10);
    });

    it('uses hanging and first line indents', function () {
        $wrapped = Text::wrap('alpha beta gamma delta', 12, '    ', '- ');

        expect($wrapped)->toBe("- alpha beta\n    gamma\n    delta");
    });

    it('keeps blank lines', function () {
        expect(Text::wrap("a\n\nb", 10))->toBe("a\n\nb");
    });
});

describe('Ansi', function () {
    it('degrades truecolor to 256 and 16 colors', function () {
        expect((new Ansi(ColorMode::TrueColor))->primary('x'))->toBe("\e[38;2;67;217;173mx\e[0m")
            ->and((new Ansi(ColorMode::Ansi256))->primary('x'))->toMatch('/^\e\[38;5;\d+mx\e\[0m$/')
            ->and((new Ansi(ColorMode::Basic))->primary('x'))->toMatch('/^\e\[(3|9)\dmx\e\[0m$/')
            ->and((new Ansi(ColorMode::None))->primary('x'))->toBe('x');
    });

    it('restores the outer style after nested resets', function () {
        $ansi = new Ansi;
        $styled = $ansi->primary('a '.$ansi->bold('b').' c');

        expect($styled)->toBe("\e[38;2;67;217;173ma \e[1mb\e[0m\e[38;2;67;217;173m c\e[0m");
    });
});

describe('Banner', function () {
    it('renders three rows and splits words when too wide', function () {
        $wide = Banner::lines('Reno Philibert', 80);
        $narrow = Banner::lines('Reno Philibert', 40);

        expect($wide)->toHaveCount(3)
            ->and($narrow)->toHaveCount(7)
            ->and(max(array_map(Text::width(...), $narrow)))->toBeLessThanOrEqual(40)
            ->and(Banner::lines('Reno Philibert', 5))->toBeNull();
    });
});

describe('HtmlConverter', function () {
    it('converts paragraphs and inline formatting', function () {
        $out = plain()->convert('<p>Hello <strong>bold</strong> and <em>italic</em> with <code>code()</code>.</p><p>Second &amp; last.</p>', 80);

        expect($out)->toBe("Hello bold and italic with `code()`.\n\nSecond & last.");
    });

    it('styles inline formatting when color is on', function () {
        $out = (new HtmlConverter(new Ansi))->convert('<p><strong>bold</strong> <code>x</code></p>', 80);

        expect($out)->toContain("\e[1;")->toContain("\e[38;2;254;165;95mx");
    });

    it('turns links into footnotes', function () {
        $html = new HtmlConverter(new Ansi(ColorMode::None));
        $out = $html->convert('<p>See <a href="https://laravel.com">the docs</a>, <a href="https://laravel.com">again</a>, <a href="https://reno.sh">https://reno.sh</a>, <a href="/blog">blog</a> and <a href="#top">top</a>.</p>', 80);

        expect($out)->toBe('See the docs[1], again[1], https://reno.sh, blog[2] and top.')
            ->and($html->footnotes(80))->toBe("[1] https://laravel.com\n[2] ".url('/blog'));
    });

    it('numbers footnotes across documents', function () {
        $html = plain();
        $html->convert('<p><a href="https://a.test">a</a></p>', 80);
        $out = $html->convert('<p><a href="https://b.test">b</a></p>', 80);

        expect($out)->toBe('b[2]');
    });

    it('renders headings', function () {
        $out = plain()->convert('<h1>Title</h1><h2>Section</h2><h3>Sub</h3><h4>Minor</h4>', 80);

        expect($out)->toBe("TITLE\n━━━━━\n\nSection\n───────\n\n▸ Sub\n\nMinor");
    });

    it('renders nested and ordered lists with hanging indents', function () {
        $out = plain()->convert('<ul><li>one<ul><li>nested <b>deep</b><ul><li>deeper</li></ul></li></ul></li><li>two</li></ul><ol start="3"><li>three</li><li>four</li></ol>', 80);

        expect($out)->toBe("• one\n  ◦ nested deep\n    ▪ deeper\n• two\n\n3. three\n4. four");
    });

    it('wraps list items under their text', function () {
        $out = plain()->convert('<ul><li>'.str_repeat('word ', 12).'</li></ul>', 20);

        foreach (array_slice(explode("\n", $out), 1) as $line) {
            expect($line)->toStartWith('  ');
        }
        expect(widest($out))->toBeLessThanOrEqual(20);
    });

    it('renders blockquotes', function () {
        $out = plain()->convert('<blockquote><p>Quoted words here</p><p>Second</p></blockquote>', 80);

        expect($out)->toBe("│ Quoted words here\n│\n│ Second");
    });

    it('preserves preformatted code and breaks long lines', function () {
        $out = plain()->convert('<pre><code class="language-php">if ($a) {'."\n".'    return 1;'."\n".'}'."\n".str_repeat('x', 50).'</code></pre>', 30);

        expect($out)->toStartWith("┌ php\n│ if (\$a) {\n│     return 1;\n│ }")
            ->and(widest($out))->toBeLessThanOrEqual(30);
    });

    it('renders hr and images', function () {
        $out = plain()->convert('<p>a</p><hr><p><img src="/img/cat.jpg" alt="A cat"></p><p><img src="https://x.test/y.png"></p>', 70);

        expect($out)->toContain(str_repeat('─', 70))
            ->toContain('[image: A cat] '.url('/img/cat.jpg'))
            ->toContain('[image: image]');
    });

    it('handles line breaks, whitespace, entities, scripts and malformed html', function () {
        $out = plain()->convert("<p>  line one<br>line   two\n\n spaced </p><script>alert(1)</script><p>unclosed <b>bold<p>next &lt;tag&gt; &eacute;", 80);

        expect($out)->toBe("line one\nline two spaced\n\nunclosed bold\n\nnext <tag> é");
    });

    it('returns empty for empty input', function () {
        expect(plain()->convert('', 80))->toBe('')
            ->and(plain()->convert(null, 80))->toBe('')
            ->and(plain()->convert('<p> </p>', 80))->toBe('');
    });

    it('never exceeds the width on messy content', function () {
        $html = '<h2>'.str_repeat('Heading ', 12).'</h2><ul><li>'.str_repeat('nested list item ', 8).'<ol><li>'.str_repeat('deeper ', 15).'</li></ol></li></ul>'
            .'<blockquote><blockquote>'.str_repeat('quote ', 30).'</blockquote></blockquote><table><tr><th>Name</th><th>Value</th></tr><tr><td>'.str_repeat('cell ', 20).'</td><td>v</td></tr></table>'
            .'<p><a href="https://example.com/'.str_repeat('long', 30).'">link</a></p>';

        foreach ([40, 41, 60] as $width) {
            $converter = new HtmlConverter(new Ansi);
            expect(widest($converter->convert($html, $width)))->toBeLessThanOrEqual($width)
                ->and(widest($converter->footnotes($width)))->toBeLessThanOrEqual($width);
        }
    });
});

describe('BlockRenderer', function () {
    it('renders every block type', function () {
        $ansi = new Ansi(ColorMode::None);
        $out = (new BlockRenderer($ansi, new HtmlConverter($ansi)))->render([
            ['type' => 'heading', 'data' => ['title' => 'Hi', 'subtitle' => 'sub', 'level' => 'h3']],
            ['type' => 'rich-text', 'data' => ['content' => '<p>Body</p>']],
            ['type' => 'markdown', 'data' => ['markdown' => '**md**']],
            ['type' => 'callout', 'data' => ['type' => 'tip', 'content' => '<p>Tip body</p>']],
            ['type' => 'code', 'data' => ['language' => 'go', 'code' => 'fmt.Println("hi")']],
            ['type' => 'image', 'data' => ['image' => 'https://x.test/a.jpg', 'alt' => 'Alt', 'caption' => 'Cap']],
            ['type' => 'image-gallery', 'data' => ['images' => [['image' => 'https://x.test/b.jpg']]]],
            ['type' => 'two-columns', 'data' => ['left' => '<p>L</p>', 'right' => '<p>R</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'unknown', 'data' => []],
        ], 60);

        expect($out)->toContain("▸ Hi\nsub")
            ->toContain('Body')
            ->toContain('md')
            ->toContain("┃ ★ tip\n┃ Tip body")
            ->toContain("┌ go\n│ fmt.Println(\"hi\")")
            ->toContain("▣ Alt\n  Cap\n  https://x.test/a.jpg")
            ->toContain('https://x.test/b.jpg')
            ->toContain("L\n\nR")
            ->toContain('· · ·');
    });

    it('renders two columns side by side when wide', function () {
        $ansi = new Ansi(ColorMode::None);
        $out = (new BlockRenderer($ansi, new HtmlConverter($ansi)))->render([
            ['type' => 'two-columns', 'data' => ['left' => '<p>Left</p>', 'right' => '<p>Right</p>']],
        ], 120);

        expect($out)->toMatch('/^Left\s+│ Right$/');
    });
});
