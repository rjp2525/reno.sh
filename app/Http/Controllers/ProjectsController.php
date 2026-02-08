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

        $query
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->get('search');
                $q->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereRelation('tags', 'name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->byType($request->get('type')))
            ->when($request->filled('status'), fn ($q) => $q->byStatus($request->get('status')))
            ->when($request->filled('tags'), function ($q) use ($request) {
                $tags = is_array($request->get('tags'))
                    ? $request->get('tags')
                    : explode(',', $request->get('tags'));

                $q->withAnyTags($tags);
            })
            ->when($request->filled('technologies'), function ($q) use ($request) {
                $technologies = is_array($request->get('technologies'))
                    ? $request->get('technologies')
                    : explode(',', $request->get('technologies'));

                $q->whereHas('technologies', fn ($tech) => $tech->whereIn('technologies.slug', $technologies));
            })
            ->when($request->boolean('featured'), fn ($q) => $q->featured());

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
