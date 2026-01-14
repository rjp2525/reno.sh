<?php

declare(strict_types=1);

use App\Enums\ContentSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('section')->default(ContentSection::PERSONAL->value);
            $table->string('slug');
            $table->string('title');
            $table->string('icon')->nullable();
            $table->json('content')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['section', 'slug']);
            $table->index(['section', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
