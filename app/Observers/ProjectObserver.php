<?php

namespace App\Observers;

use App\Models\Project;
use App\Support\Content\Blocks;

class ProjectObserver
{
    public function saving(Project $project): void
    {
        // Older callers (and the factory) may only set a plain description.
        if (blank($project->content) && filled($project->description) && $project->isDirty('description')) {
            $project->content = Blocks::fromMarkdown((string) $project->description);
        }

        if ($project->isDirty('content')) {
            $project->content = Blocks::normalize($project->content);
            // Plain-text mirror for search and card excerpts.
            $project->description = Blocks::toText($project->content) ?: null;
        }
    }
}
