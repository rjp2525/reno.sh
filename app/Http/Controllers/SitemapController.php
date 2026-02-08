<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogSeries;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = Sitemap::create()
            ->add(Url::create('/')->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/about/professional')->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/about/personal')->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/about/hobbies')->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/projects')->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/gallery')->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));

        Project::published()->get()->each(function (Project $project) use ($sitemap) {
            $sitemap->add(
                Url::create("/projects/{$project->slug}")
                    ->setLastModificationDate($project->updated_at)
                    ->setPriority(0.6)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );
        });

        $sitemap->add(Url::create('/blog')->setPriority(0.9)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));

        BlogCategory::active()->get()->each(function (BlogCategory $category) use ($sitemap) {
            $sitemap->add(
                Url::create("/blog/{$category->slug}")
                    ->setPriority(0.7)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            );
        });

        Post::published()->with('category')->get()->each(function (Post $post) use ($sitemap) {
            $url = $post->category
                ? "/blog/{$post->category->slug}/{$post->slug}"
                : "/blog/post/{$post->slug}";

            $sitemap->add(
                Url::create($url)
                    ->setLastModificationDate($post->updated_at)
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );
        });

        BlogSeries::published()->get()->each(function (BlogSeries $series) use ($sitemap) {
            $sitemap->add(
                Url::create("/blog/series/{$series->slug}")
                    ->setPriority(0.6)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            );
        });

        Photo::published()->processed()->get()->each(function (Photo $photo) use ($sitemap) {
            $sitemap->add(
                Url::create("/gallery/{$photo->slug}")
                    ->setLastModificationDate($photo->updated_at)
                    ->setPriority(0.6)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );
        });

        return response($sitemap->render(), 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
