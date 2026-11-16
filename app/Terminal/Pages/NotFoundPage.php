<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Terminal\Banner;
use App\Terminal\Context;
use App\Terminal\Text;

/**
 * 404 (and other error statuses) for terminal clients.
 */
final class NotFoundPage extends Page
{
    public function __construct(Context $ctx, private readonly string $path = '/', private readonly int $code = 404)
    {
        parent::__construct($ctx);
    }

    public function section(): ?string
    {
        return null;
    }

    public function status(): int
    {
        return $this->code;
    }

    protected function body(int $width): string
    {
        $banner = Banner::lines((string) $this->code, $width) ?? [(string) $this->code];
        $span = max(array_map(fn ($l) => mb_strwidth($l), $banner));
        $art = implode("\n", array_map(fn ($l) => $this->ansi->gradient($l, 'orange', 'pink', $span, 'bold'), $banner));

        $message = match ($this->code) {
            404 => 'page not found',
            403 => 'forbidden',
            429 => 'too many requests, slow down a little',
            503 => 'down for maintenance, back soon',
            default => 'something went wrong on my end',
        };

        $path = '/'.ltrim($this->path, '/');

        return $this->stack([
            $art,
            Text::wrap($this->ansi->style('error: ', 'pink', 'bold').$this->ansi->white($message), $width),
            $this->code === 404 ? $this->command('cd '.$path, $width)."\n"
                .Text::wrap($this->ansi->slate('cd: no such file or directory: '.$path), $width) : '',
            $this->comment('try one of these instead:', $width),
        ]);
    }
}
