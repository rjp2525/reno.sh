<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\About\EducationResource;
use App\Models\Education;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** About → Professional → education. */
class EducationController extends Controller
{
    private const LISTS = ['achievements', 'projects', 'extracurriculars'];

    public function index(): AnonymousResourceCollection
    {
        return EducationResource::collection(Education::query()
            ->orderByRaw('case when graduated is null then 0 else 1 end')
            ->orderByDesc('graduated')->orderByDesc('started')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $education = Education::create($this->validated($request));

        return (new EducationResource($education->refresh()))->response()->setStatusCode(201);
    }

    public function show(Education $education): EducationResource
    {
        return new EducationResource($education);
    }

    public function update(Request $request, Education $education): EducationResource
    {
        $education->update($this->validated($request, $education));

        return new EducationResource($education->refresh());
    }

    public function destroy(Education $education): Response
    {
        $education->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Education $education = null): array
    {
        $required = $education === null ? 'required' : 'sometimes';
        $started = $request->input('started', $education?->started?->toDateString());

        $rules = [
            'school_name' => [$required, 'string', 'max:255'],
            'degree' => [$required, 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'minor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'started' => ['sometimes', 'nullable', 'date'],
            'graduated' => ['sometimes', 'nullable', 'date', ...($started ? ['after_or_equal:'.$started] : [])],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];

        foreach (self::LISTS as $list) {
            $rules[$list] = ['sometimes', 'array', 'max:50'];
            $rules["{$list}.*"] = ['required', 'string', 'max:1000'];
        }

        return $request->validate($rules);
    }
}
