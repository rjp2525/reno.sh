<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\Project;

final class ProjectsPage extends Page
{
    public function section(): ?string
    {
        return 'projects';
    }

    protected function body(int $width): string
    {
        $type = $this->ctx->query('type');

        $projects = Project::published()
            ->with('technologies')
            ->when(is_string($type) && $type !== '', fn ($q) => $q->byType($type))
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        $entries = $projects->map(fn (Project $p) => $this->entry(
            $this->ansi->style($p->title, 'white', 'bold').($p->is_featured ? $this->ansi->orange(' ★') : ''),
            [
                $this->ansi->slate($this->meta([ProjectPage::typeLabel($p), $p->status])),
                $p->summary,
                $p->technologies->isNotEmpty() ? $this->ansi->primary($p->technologies->pluck('name')->implode(' · ')) : null,
                $this->prompt($this->ctx->curl('/projects/'.$p->slug)),
            ],
            $width,
        ))->all();

        return $this->stack([
            $this->title('_projects', $width, $projects->count().' '.str('project')->plural($projects->count()).($type ? " of type {$type}" : '').', featured first'),
            $entries === [] ? $this->comment('no projects to show yet', $width) : $this->group(null, $entries, $width),
        ]);
    }
}
