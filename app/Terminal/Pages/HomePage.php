<?php

declare(strict_types=1);

namespace App\Terminal\Pages;

use App\Models\Post;
use App\Models\Project;
use App\Terminal\Banner;
use App\Terminal\Text;
use Illuminate\Support\Str;

final class HomePage extends Page
{
    public function section(): ?string
    {
        return 'hello';
    }

    protected function body(int $width): string
    {
        return $this->stack([
            $this->intro($width),
            $this->code($width),
            $this->latestPosts($width),
            $this->featuredProjects($width),
        ]);
    }

    private function intro(int $width): string
    {
        $name = (string) config('site.name');
        $out = [$this->ansi->white("Howdy there, I'm"), ''];

        $banner = Banner::lines($name, $width);
        if ($banner === null) {
            $out[] = Text::wrap($this->ansi->style($name, 'white', 'bold'), $width);
        } else {
            $span = max(array_map(fn ($l) => mb_strwidth($l), $banner));
            foreach ($banner as $line) {
                $out[] = $line === '' ? '' : $this->ansi->gradient($line, 'primary', 'blue', $span, 'bold');
            }
        }

        $out[] = '';
        $out[] = Text::wrap($this->ansi->purple('> ').$this->ansi->style((string) config('site.title'), 'primary'), $width, '  ');

        return implode("\n", $out);
    }

    private function code(int $width): string
    {
        $tagline = Str::lower(rtrim((string) config('site.tagline'), '.'));
        $lines = [$this->comment($tagline, $width)];

        if (filled(config('site.location'))) {
            $lines[] = $this->comment('based in '.config('site.location'), $width);
        }

        $socials = collect(config('site.socials', []));
        $github = $socials->first(fn ($s) => strtolower($s['label']) === 'github');

        if ($github) {
            $lines[] = $this->comment('check out my profile on Github:', $width);
            $lines[] = Text::wrap($this->constant('githubLink', '"'.$github['url'].'"').';', $width, '  ');
        }

        $others = $socials->reject(fn ($s) => $s === $github);
        if ($others->isNotEmpty()) {
            $lines[] = '';
            $lines[] = $this->comment('find me around the web:', $width);
            $lines[] = $this->constant('socials', '{');
            foreach ($others as $social) {
                $key = Str::camel(Str::lower($social['label']));
                $lines[] = Text::wrap($this->ansi->style($key, 'primary').$this->ansi->white(': ').$this->ansi->pink('"'.$social['url'].'"').$this->ansi->white(','), $width, '    ', '  ');
            }
            $lines[] = $this->ansi->white('};');
        }

        return implode("\n", $lines);
    }

    private function constant(string $name, string $value): string
    {
        return $this->ansi->blue('const ').$this->ansi->style($name, 'primary').$this->ansi->white(' = ').($value === '{' ? $this->ansi->white($value) : $this->ansi->pink($value));
    }

    private function latestPosts(int $width): string
    {
        $posts = Post::published()->with('category')->ordered()->limit(3)->get();
        if ($posts->isEmpty()) {
            return '';
        }

        $out = [];
        foreach ($posts as $post) {
            $out[] = $this->entry($this->ansi->style($post->title, 'white', 'bold'), [
                $this->ansi->slate($this->meta([$this->date($post->published_at), $post->reading_time ? $post->reading_time.' min read' : null, $post->category?->name])),
                $this->prompt($this->ctx->curl(PostPage::path($post))),
            ], $width);
        }

        return $this->group('latest from the blog', $out, $width);
    }

    private function featuredProjects(int $width): string
    {
        $projects = Project::published()->featured()->orderBy('sort_order')->limit(3)->get();
        if ($projects->isEmpty()) {
            return '';
        }

        $out = [];
        foreach ($projects as $project) {
            $out[] = $this->entry($this->ansi->style($project->title, 'white', 'bold'), [
                $project->summary ? $this->ansi->slate($project->summary) : null,
                $this->prompt($this->ctx->curl('/projects/'.$project->slug)),
            ], $width);
        }

        return $this->group('featured projects', $out, $width);
    }
}
