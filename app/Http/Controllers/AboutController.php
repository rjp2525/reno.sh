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

        $pages = AboutPage::all();

        return Inertia::render('About', [
            'experience' => $experience,
            'skills' => $skills,
            'pages' => $pages,
        ]);
    }
}
