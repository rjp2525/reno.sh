<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Models\ContentPage;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Skill;
use App\Notifications\ContactFormNotification;
use App\Support\Content\Blocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class PublicController extends Controller
{
    public function site(): JsonResponse
    {
        return response()->json(['data' => [
            'name' => config('site.name'),
            'title' => config('site.title'),
            'tagline' => config('site.tagline'),
            'location' => config('site.location'),
            'email' => config('site.email'),
            'url' => config('app.url'),
            'socials' => config('site.socials'),
            'ssh' => config('site.ssh'),
            'sections' => ['about', 'projects', 'blog', 'gallery', 'prints', 'contact'],
        ]]);
    }

    public function about(): JsonResponse
    {
        $pages = ContentPage::query()->published()->orderBy('section')->orderBy('order')->get()
            ->map(fn (ContentPage $p) => [
                'section' => $p->section instanceof ContentSection ? $p->section->value : $p->section,
                'slug' => $p->slug,
                'title' => $p->title,
                'icon' => $p->icon,
                'text' => Blocks::toText($p->content),
                'markdown' => Blocks::toMarkdown($p->content),
            ]);

        $date = fn ($d) => $d?->toDateString();

        return response()->json(['data' => [
            'pages' => $pages,
            'experience' => Experience::all()->map(fn ($e) => [
                'id' => (string) $e->id, 'title' => $e->title, 'company' => $e->company, 'location' => $e->location,
                'start' => $date($e->start), 'end' => $date($e->end), 'responsibilities' => $e->responsibilities ?? [],
            ]),
            'skills' => Skill::all()->map(fn ($s) => [
                'id' => (string) $s->id, 'category' => $s->category, 'name' => $s->name,
                'proficiency' => $s->proficiency instanceof \BackedEnum ? $s->proficiency->value : $s->proficiency,
                'started' => $date($s->started), 'ended' => $date($s->ended),
            ]),
            'education' => Education::all()->map(fn ($e) => [
                'id' => (string) $e->id, 'school_name' => $e->school_name, 'location' => $e->location, 'degree' => $e->degree,
                'minor' => $e->minor, 'started' => $date($e->started), 'graduated' => $date($e->graduated),
                'description' => $e->description, 'achievements' => $e->achievements ?? [],
                'projects' => $e->projects ?? [], 'extracurriculars' => $e->extracurriculars ?? [],
            ]),
        ]]);
    }

    public function contact(ContactRequest $request): JsonResponse
    {
        $data = $request->validated();

        Notification::route('mail', config('mail.contact_to', config('site.email')))
            ->notify(new ContactFormNotification(
                name: $data['name'],
                email: $data['email'],
                subject: $data['subject'] ?? 'New message from '.parse_url(config('app.url'), PHP_URL_HOST),
                contactMessage: $data['message'],
            ));

        return response()->json(['data' => ['message' => "Thanks for reaching out! I'll get back to you soon."]], 202);
    }
}
