<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\About\ExperienceResource;
use App\Models\Experience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** About → Professional → work history. */
class ExperienceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ExperienceResource::collection(Experience::query()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $experience = Experience::create($this->validated($request));

        return (new ExperienceResource($experience->refresh()))->response()->setStatusCode(201);
    }

    public function show(Experience $experience): ExperienceResource
    {
        return new ExperienceResource($experience);
    }

    public function update(Request $request, Experience $experience): ExperienceResource
    {
        $experience->update($this->validated($request, $experience));

        return new ExperienceResource($experience->refresh());
    }

    public function destroy(Experience $experience): Response
    {
        $experience->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Experience $experience = null): array
    {
        $required = $experience === null ? 'required' : 'sometimes';
        $start = $request->input('start', $experience?->start?->toDateString());

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'company' => [$required, 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start' => [$required, 'date'],
            'end' => ['sometimes', 'nullable', 'date', ...($start ? ['after_or_equal:'.$start] : [])],
            'responsibilities' => ['sometimes', 'array', 'max:50'],
            'responsibilities.*' => ['required', 'string', 'max:2000'],
        ]);
    }
}
