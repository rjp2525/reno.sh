<?php

declare(strict_types=1);

namespace App\Terminal;

use App\Enums\ContentSection;
use App\Models\BlogCategory;
use App\Models\BlogSeries;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Project;
use App\Terminal\Pages\BlogPage;
use App\Terminal\Pages\ContactPage;
use App\Terminal\Pages\ContentSectionPage;
use App\Terminal\Pages\GalleryPage;
use App\Terminal\Pages\HomePage;
use App\Terminal\Pages\NotFoundPage;
use App\Terminal\Pages\Page;
use App\Terminal\Pages\PhotoPage;
use App\Terminal\Pages\PostPage;
use App\Terminal\Pages\ProfessionalPage;
use App\Terminal\Pages\ProjectPage;
use App\Terminal\Pages\ProjectsPage;
use App\Terminal\Pages\SeriesPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * Maps a matched web route to its terminal page. Route parameters may be raw
 * slugs (middleware ran before SubstituteBindings) or bound models (after);
 * either way content is re-queried with the published constraints so drafts
 * never leak.
 */
final class Router
{
    /** Route names with a terminal rendition. */
    public const ROUTES = [
        'home', 'about.professional', 'about.personal', 'about.hobbies',
        'projects', 'projects.show', 'gallery', 'gallery.show',
        'blog.index', 'blog.category', 'blog.show', 'blog.show.uncategorized', 'blog.series.show',
        'contact',
    ];

    public static function handles(?Route $route): bool
    {
        if ($route === null) {
            return false;
        }

        // `/about` is an unnamed redirect closure (named "about." by its group).
        return in_array($route->getName(), self::ROUTES, true) || $route->uri() === 'about';
    }

    public function resolve(Request $request, Context $ctx): Page
    {
        $route = $request->route();
        $name = $route instanceof Route ? $route->getName() : null;
        $param = fn (string $key) => $route instanceof Route ? self::key($route->parameter($key)) : null;
        $notFound = fn () => new NotFoundPage($ctx, $request->path());

        if ($route instanceof Route && $route->uri() === 'about') {
            $name = 'about.professional';
        }

        switch ($name) {
            case 'home':
                return new HomePage($ctx);

            case 'about.professional':
                return new ProfessionalPage($ctx, $param('tab'));

            case 'about.personal':
                return new ContentSectionPage($ctx, ContentSection::PERSONAL, $param('tab'));

            case 'about.hobbies':
                return new ContentSectionPage($ctx, ContentSection::HOBBIES, $param('tab'));

            case 'projects':
                return new ProjectsPage($ctx);

            case 'projects.show':
                $project = Project::published()->where('slug', $param('project'))->first();

                return $project ? new ProjectPage($ctx, $project) : $notFound();

            case 'gallery':
                return new GalleryPage($ctx);

            case 'gallery.show':
                $photo = Photo::published()->processed()->where('slug', $param('photo'))->first();

                return $photo ? new PhotoPage($ctx, $photo) : $notFound();

            case 'blog.index':
                return new BlogPage($ctx);

            case 'blog.category':
                $category = BlogCategory::where('slug', $param('category'))->first();

                return $category ? new BlogPage($ctx, $category) : $notFound();

            case 'blog.show':
                $category = BlogCategory::where('slug', $param('category'))->first();
                $post = $category ? Post::published()->where('slug', $param('post'))->where('blog_category_id', $category->id)->first() : null;

                return $post ? new PostPage($ctx, $post) : $notFound();

            case 'blog.show.uncategorized':
                $post = Post::published()->where('slug', $param('post'))->first();

                return $post ? new PostPage($ctx, $post) : $notFound();

            case 'blog.series.show':
                $series = BlogSeries::published()->where('slug', $param('series'))->first();

                return $series ? new SeriesPage($ctx, $series) : $notFound();

            case 'contact':
                return new ContactPage($ctx);
        }

        return $notFound();
    }

    private static function key(mixed $value): ?string
    {
        if ($value instanceof Model) {
            return (string) ($value->getAttribute('slug') ?? $value->getRouteKey());
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
