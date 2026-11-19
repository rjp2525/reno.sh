<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\Project;
use App\Terminal\BlockRenderer;
use App\Terminal\Context;
use App\Terminal\Text;

final class ProjectPage extends Page
{
    public function __construct(Context $ctx, private readonly Project $project)
    {
        parent::__construct($ctx);
    }

    public function section(): ?string
    {
        return 'projects';
    }

    public static function typeLabel(Project $project): ?string
    {
        return $project->type ? str_replace('_', ' ', $project->type->value) : null;
    }

    protected function body(int $width): string
    {
        $p = $this->project->loadMissing(['technologies', 'tags']);

        $timeline = null;
        if ($p->start_date) {
            $end = $p->is_ongoing || ! $p->end_date ? 'present' : $p->end_date->format('M Y');
            $timeline = $p->start_date->format('M Y').' – '.$end;
        }

        $links = array_filter([
            'website' => $p->url,
            'source' => $p->github_url,
            'demo' => $p->demo_url,
        ]);

        $details = $this->layout->keyValue([
            'type' => self::typeLabel($p),
            'status' => $p->status ? str_replace('_', ' ', (string) $p->status) : null,
            'timeline' => $timeline,
            'stack' => $p->technologies->isNotEmpty() ? $this->ansi->primary($p->technologies->pluck('name')->implode(' · ')) : null,
        ] + array_map(fn ($url) => $this->ansi->style($url, 'pink', 'underline'), $links), $width);

        $tags = $p->tags->map(fn ($t) => '#'.($t->name['en'] ?? $t->name))->implode(' ');

        $gallery = collect($p->gallery ?? [])
            ->map(fn ($path) => BlockRenderer::url(is_array($path) ? ($path['image'] ?? null) : $path))
            ->filter()
            ->map(fn ($url) => Text::wrap($this->ansi->purple('▣ ').$this->ansi->slate($url), $width, '  '))
            ->implode("\n");

        $related = Project::published()
            ->where('id', '!=', $p->id)
            ->where(fn ($q) => $q->where('type', $p->type)->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $p->tags->pluck('id'))))
            ->limit(3)
            ->get()
            ->map(fn (Project $r) => $this->entry($this->ansi->white($r->title), [$this->prompt($this->ctx->curl('/projects/'.$r->slug))], $width))
            ->all();

        return $this->stack([
            $this->title('_projects / '.$p->slug, $width),
            Text::wrap($this->ansi->style($p->title, 'white', 'bold'), $width)
                .($p->summary ? "\n".Text::wrap($this->ansi->style($p->summary, 'slate', 'italic'), $width) : ''),
            $details,
            filled($p->content) ? $this->blocks->render($p->content, $width) : $this->richOrPlain($p->description, $width),
            $gallery !== '' ? $this->layout->section('gallery', $width)."\n\n".$gallery : '',
            $tags !== '' ? Text::wrap($this->ansi->slate($tags), $width) : '',
            $this->group('related', $related, $width),
            $this->comment('view it on the web: '.$this->ctx->url('/projects/'.$p->slug), $width)."\n"
                .$this->command($this->ctx->curl('/projects'), $width),
        ]);
    }
}
