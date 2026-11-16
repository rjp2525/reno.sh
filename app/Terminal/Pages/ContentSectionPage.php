<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Enums\ContentSection;
use App\Models\ContentPage;
use App\Terminal\Context;

/**
 * /about/personal and /about/hobbies: published content pages of a section,
 * all of them, or just `{tab}` when it names one.
 */
final class ContentSectionPage extends AboutPage
{
    public function __construct(Context $ctx, private readonly ContentSection $contentSection, private readonly ?string $tab = null)
    {
        parent::__construct($ctx);
    }

    protected function about(): string
    {
        return $this->contentSection->value;
    }

    protected function body(int $width): string
    {
        $pages = ContentPage::query()->section($this->contentSection)->published()->get();
        $active = $pages->firstWhere('slug', $this->tab);
        $shown = $active ? collect([$active]) : $pages;

        $chunks = [$this->aboutHeader($width, $active ? strtolower($active->title) : null)];

        if ($shown->isEmpty()) {
            $chunks[] = $this->comment('nothing here yet, check back soon', $width);
        }

        foreach ($shown as $page) {
            $content = $this->blocks->render($page->content, $width);
            $chunks[] = $this->layout->section(strtolower($page->title), $width).($content !== '' ? "\n\n".$content : '');
        }

        $others = $active ? $pages->where('slug', '!=', $active->slug)->pluck('slug')->all() : [];
        $chunks[] = $this->aboutFooter($width, $others);

        return $this->stack($chunks);
    }
}
