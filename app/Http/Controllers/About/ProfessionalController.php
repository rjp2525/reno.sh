<?php

declare(strict_types=1);

namespace App\Http\Controllers\About;

use App\Http\Controllers\Controller;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Skill;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalController extends Controller
{
    private const VALID_TABS = ['experience', 'skills', 'education'];

    public function __invoke(Request $request, ?string $tab = null): Response
    {
        $activeTab = $this->resolveTab($tab);

        $experience = Experience::all();

        $skills = Skill::all()
            ->groupBy('category')
            ->map(fn ($skills, $category) => [
                'title' => $category,
                'skills' => $skills,
            ])->values();

        $education = Education::all();

        return Inertia::render('about/Professional', [
            'activeTab' => $activeTab,
            'tabs' => $this->getTabs(),
            'experience' => $experience,
            'skills' => $skills,
            'education' => $education,
        ]);
    }

    private function resolveTab(?string $tab): string
    {
        if ($tab === null || ! in_array($tab, self::VALID_TABS, true)) {
            return self::VALID_TABS[0];
        }

        return $tab;
    }

    /**
     * @return array<string, string>
     */
    private function getTabs(): array
    {
        return [
            'experience' => 'experience',
            'skills' => 'skills',
            'education' => 'education',
        ];
    }
}
