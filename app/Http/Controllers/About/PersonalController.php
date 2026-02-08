<?php

declare(strict_types=1);

namespace App\Http\Controllers\About;

use App\Enums\ContentSection;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonalController extends Controller
{
    public function __invoke(Request $request, ?string $tab = null): Response
    {
        $pages = ContentPage::query()
            ->section(ContentSection::PERSONAL)
            ->published()
            ->get();

        $tabs = $pages->pluck('title', 'slug')->toArray();
        $validTabs = array_keys($tabs);
        $activeTab = ($tab !== null && in_array($tab, $validTabs, true))
            ? $tab
            : ($validTabs[0] ?? '');

        $activePage = $pages->firstWhere('slug', $activeTab);

        return Inertia::render('about/Personal', [
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
}
