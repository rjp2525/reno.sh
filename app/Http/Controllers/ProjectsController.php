<?php

namespace App\Http\Controllers;

use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\Technology;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Tags\Tag;

class ProjectsController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Project::published()
            ->with(['tags', 'technologies'])
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereRelation('tags', 'name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->byType($request->get('type'));
        }

        if ($request->filled('status')) {
            $query->byStatus($request->get('status'));
        }

        if ($request->filled('tags')) {
            $tags = is_array($request->get('tags'))
                ? $request->get('tags')
                : explode(',', $request->get('tags'));

            $query->withAnyTags($tags);
        }

        if ($request->filled('technologies')) {
            $technologies = is_array($request->get('technologies'))
                ? $request->get('technologies')
                : explode(',', $request->get('technologies'));

            $query->whereHas('technologies', function ($q) use ($technologies) {
                $q->whereIn('technologies.slug', $technologies);
            });
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $projects = $query->paginate(12)->withQueryString();

        $allTags = Tag::where('type', 'project')
            ->orderBy('name->en')
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name['en'] ?? $tag->name,
                    'slug' => $tag->slug['en'] ?? $tag->slug,
                ];
            });

        $allTechnologies = Technology::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(function ($tech) {
                return [
                    'id' => $tech->id,
                    'name' => $tech->name,
                    'slug' => $tech->slug,
                    'category' => $tech->category,
                    'color' => $tech->color,
                ];
            });

        $projectTypes = collect(ProjectType::cases())->map(function ($type) {
            return [
                'value' => $type->value,
                'label' => ucfirst(str_replace('_', ' ', $type->value)),
            ];
        });

        return Inertia::render('Projects', [
            'projects' => $projects,
            'filters' => [
                'search' => $request->get('search'),
                'type' => $request->get('type'),
                'status' => $request->get('status'),
                'tags' => $request->get('tags'),
                'technologies' => $request->get('technologies'),
                'featured' => $request->boolean('featured'),
            ],
            'filterOptions' => [
                'tags' => $allTags,
                'technologies' => $allTechnologies,
                'types' => $projectTypes,
            ],
        ]);
    }
}
