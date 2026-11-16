<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SkillProficiency;
use App\Http\Controllers\Controller;
use App\Http\Resources\About\SkillResource;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** About → Professional → skills. */
class SkillController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SkillResource::collection(Skill::query()->orderBy('category')->orderBy('name')->get())
            ->additional(['meta' => [
                'categories' => Skill::query()->distinct()->orderBy('category')->pluck('category')->values(),
                'proficiencies' => collect(SkillProficiency::cases())
                    ->map(fn (SkillProficiency $p) => ['value' => $p->value, 'label' => $p->getLabel()])
                    ->values(),
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $skill = Skill::create($this->validated($request));

        return (new SkillResource($skill->refresh()))->response()->setStatusCode(201);
    }

    public function show(Skill $skill): SkillResource
    {
        return new SkillResource($skill);
    }

    public function update(Request $request, Skill $skill): SkillResource
    {
        $skill->update($this->validated($request, $skill));

        return new SkillResource($skill->refresh());
    }

    public function destroy(Skill $skill): Response
    {
        $skill->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Skill $skill = null): array
    {
        $required = $skill === null ? 'required' : 'sometimes';
        $started = $request->input('started', $skill?->started?->toDateString());

        return $request->validate([
            'category' => [$required, 'string', 'max:100'],
            'name' => [$required, 'string', 'max:100'],
            'proficiency' => [$required, Rule::enum(SkillProficiency::class)],
            'started' => [$required, 'date'],
            'ended' => ['sometimes', 'nullable', 'date', ...($started ? ['after_or_equal:'.$started] : [])],
        ]);
    }
}
