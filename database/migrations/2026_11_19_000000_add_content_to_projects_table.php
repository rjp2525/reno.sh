<?php

use App\Support\Content\Blocks;
use App\Support\Content\HtmlConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projects get the same block content as blog posts. Existing plain-text (or HTML)
 * descriptions become a single Markdown block; `description` stays as the plain-text
 * version used for search and card excerpts (kept in sync by ProjectObserver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('content')->nullable()->after('description');
        });

        DB::table('projects')->whereNotNull('description')->where('description', '!=', '')->orderBy('id')
            ->each(function ($project) {
                $text = (string) $project->description;
                $markdown = $text !== strip_tags($text) ? HtmlConverter::toMarkdown($text) : $text;

                DB::table('projects')->where('id', $project->id)
                    ->update(['content' => json_encode(Blocks::fromMarkdown($markdown))]);
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
