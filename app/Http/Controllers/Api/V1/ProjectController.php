<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProjectResource::collection(Project::published()
            ->with('technologies')
            ->when($request->query('type'), fn ($q, $type) => $q->byType($type))
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->get());
    }

    public function show(string $slug): ProjectResource
    {
        return (new ProjectResource(Project::published()->with('technologies')->where('slug', $slug)->firstOrFail()))->detailed();
    }
}
