<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectShowController extends Controller
{
    public function __invoke(Request $request, Project $project)
    {
        $project->load(['tags', 'technologies']);

        $relatedProjects = Project::published()
            ->where('id', '!=', $project->id)
            ->where(function ($query) use ($project) {
                $query->where('type', $project->type)
                    ->orWhereHas('tags', function ($tagQuery) use ($project) {
                        $tagQuery->whereIn('tags.id', $project->tags->pluck('id'));
                    });
            })
            ->with(['tags', 'technologies'])
            ->limit(3)
            ->get();

        return Inertia::render('ProjectShow', [
            'project' => $project,
            'relatedProjects' => $relatedProjects,
        ]);
    }
}
