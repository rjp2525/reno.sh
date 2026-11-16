<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Terminal\Text;

/**
 * Shared chrome for the three about sections.
 */
abstract class AboutPage extends Page
{
    protected const SECTIONS = ['professional', 'personal', 'hobbies'];

    abstract protected function about(): string;

    public function section(): ?string
    {
        return 'about';
    }

    protected function aboutHeader(int $width, ?string $tab = null): string
    {
        $path = '_about / '.$this->about().($tab ? ' / '.$tab : '');
        $tabs = [];
        foreach (self::SECTIONS as $section) {
            $tabs[] = $section === $this->about()
                ? $this->ansi->style($section, 'orange', 'bold')
                : $this->ansi->slate($section);
        }

        return $this->title($path, $width)."\n".Text::wrap(implode($this->ansi->slate(' · '), $tabs), $width);
    }

    protected function aboutFooter(int $width, array $tabs = []): string
    {
        $lines = [];
        foreach ($tabs as $tab) {
            $lines[] = $this->command($this->ctx->curl('/about/'.$this->about().'/'.$tab), $width);
        }
        foreach (self::SECTIONS as $section) {
            if ($section !== $this->about()) {
                $lines[] = $this->command($this->ctx->curl('/about/'.$section), $width);
            }
        }

        return $this->layout->section('more about me', $width)."\n\n".implode("\n", $lines);
    }
}
