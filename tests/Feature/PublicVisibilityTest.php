<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;

it('hides draft posts from the public but lets admins preview them', function () {
    $post = Post::factory()->create(['status' => PostStatus::DRAFT, 'blog_category_id' => null, 'published_at' => null]);

    $this->get("/blog/post/{$post->slug}")->assertNotFound();

    config(['site.admin_emails' => ['admin@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.com']))
        ->get("/blog/post/{$post->slug}")
        ->assertOk();
});

it('hides unpublished projects', function () {
    $project = Project::factory()->create(['is_published' => false]);

    $this->get("/projects/{$project->slug}")->assertNotFound();
});

it('serves the blog feed instead of treating it as a category', function () {
    $this->get('/blog/feed.xml')->assertOk()->assertHeader('content-type', 'application/xml;charset=UTF-8');
});
