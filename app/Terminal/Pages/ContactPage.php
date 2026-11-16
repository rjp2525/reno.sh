<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Terminal\Text;
use Illuminate\Support\Str;

final class ContactPage extends Page
{
    public function section(): ?string
    {
        return 'contact';
    }

    protected function body(int $width): string
    {
        $email = (string) config('site.email');

        $socials = collect(config('site.socials', []))
            ->map(fn ($s) => Text::wrap(
                $this->ansi->style(Str::camel(Str::lower($s['label'])), 'primary').$this->ansi->white(': ').$this->ansi->pink('"'.$s['url'].'"').$this->ansi->white(','),
                $width,
                '    ',
                '  ',
            ));

        $code = [
            $this->comment('reach out by email', $width),
            Text::wrap($this->ansi->blue('const ').$this->ansi->primary('email').$this->ansi->white(' = ').$this->ansi->pink('"'.$email.'"').$this->ansi->white(';'), $width, '  '),
        ];
        if ($socials->isNotEmpty()) {
            $code[] = '';
            $code[] = $this->comment('or find me on', $width);
            $code[] = $this->ansi->blue('const ').$this->ansi->primary('socials').$this->ansi->white(' = {');
            $code[] = $socials->implode("\n");
            $code[] = $this->ansi->white('};');
        }

        return $this->stack([
            $this->title('_contact-me', $width, 'say hello, ask a question or pitch an idea'),
            implode("\n", $code),
            $this->layout->section('send a message from your terminal', $width)."\n\n"
                .$this->comment('copy, edit and paste this into your shell:', $width)."\n\n"
                .Text::breakLines($this->example(), $width),
            $this->comment('name, email and message are required; messages are rate limited to 5 per minute', $width),
        ]);
    }

    /** Copy-pasteable `curl -X POST` against the JSON API. */
    private function example(): string
    {
        $json = json_encode([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subject' => 'Hello from the terminal',
            'message' => 'Found your site with curl. Very cool!',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $json = str_replace("\n", "\n  ", (string) preg_replace('/^    /m', '  ', (string) $json));

        $lines = [
            $this->ansi->white('curl -X POST '.$this->ctx->url('/api/v1/contact').' \\'),
            $this->ansi->white('  -H "Content-Type: application/json" \\'),
            $this->ansi->white('  -H "Accept: application/json" \\'),
            $this->ansi->white("  -d '").$this->ansi->orange($json).$this->ansi->white("'"),
        ];

        return implode("\n", $lines);
    }
}
