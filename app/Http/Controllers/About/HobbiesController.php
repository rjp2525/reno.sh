<?php

declare(strict_types=1);

namespace App\Http\Controllers\About;

use App\Enums\ContentSection;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HobbiesController extends Controller
{
    public function __invoke(Request $request, ?string $tab = null): Response
    {
        $pages = ContentPage::query()
            ->section(ContentSection::HOBBIES)
            ->published()
            ->get();

        $tabs = $pages->pluck('title', 'slug')->toArray();

        // If no content pages exist yet, use default tabs
        if (empty($tabs)) {
            $tabs = ContentSection::HOBBIES->getDefaultTabs();
        }

        $activeTab = $this->resolveTab($tab, array_keys($tabs));

        $activePage = $pages->firstWhere('slug', $activeTab);

        return Inertia::render('about/Hobbies', [
            'activeTab' => $activeTab,
            'tabs' => $tabs,
            'content' => $activePage?->content,
            'pages' => $pages->map(fn (ContentPage $page) => [
                'slug' => $page->slug,
                'title' => strtolower($page->title),
                'icon' => $page->icon,
                'content' => $page->content,
            ]),
        ]);
    }

    private function resolveTab(?string $tab, array $validTabs): string
    {
        if ($tab === null || ! in_array($tab, $validTabs, true)) {
            return $validTabs[0] ?? 'overlanding';
        }

        return $tab;
    }
}
