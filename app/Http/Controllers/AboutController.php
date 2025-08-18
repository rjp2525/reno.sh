<?php

namespace App\Http\Controllers;

use App\Models\AboutPage;
use App\Models\Experience;
use App\Models\Skill;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $experience = Experience::all();

        $skills = Skill::all()
            ->groupBy('category')
            ->map(fn ($skills, $category) => [
                'title' => $category,
                'skills' => $skills,
            ])->values();

        $sections = AboutPage::orderBy('order')->get()
            ->groupBy('section')
            ->map(function ($pages, $sectionName) use ($experience, $skills) {
                $sectionData = [
                    'name' => $sectionName,
                    'icon' => $this->getSectionIcon($sectionName),
                    'pages' => $pages->sortBy('order')->map(function ($page) {
                        return [
                            'id' => $page->id,
                            'title' => $page->title,
                            'slug' => $page->slug,
                            'content' => $page->content,
                        ];
                    })->values()->toArray(),
                ];

                if ($sectionName === 'professional') {
                    $sectionData['pages'][] = [
                        'slug' => 'experience',
                        'title' => 'Experience',
                        'content' => $experience,
                        'type' => 'experience',
                    ];
                    $sectionData['pages'][] = [
                        'slug' => 'skills',
                        'title' => 'Skills',
                        'content' => $skills,
                        'type' => 'skills',
                    ];
                }

                return $sectionData;
            })->values();

        return Inertia::render('About', [
            'sections' => $sections,
        ]);
    }

    private function getSectionIcon(string $section): string
    {
        return match ($section) {
            'professional' => 'code-browser',
            'personal' => 'person',
            'hobbies' => 'hobbies',
            default => 'folder'
        };
    }
}
