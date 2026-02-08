<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technologies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable(); // frontend, backend, database, devops, etc.
            $table->string('color')->nullable(); // hex color for display
            $table->string('icon')->nullable(); // icon class or path
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('project_technology', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('technology_id')->constrained()->onDelete('cascade');
            $table->string('proficiency_level')->nullable(); // beginner, intermediate, advanced, expert
            $table->boolean('is_primary')->default(false); // mark primary technologies
            $table->timestamps();

            $table->unique(['project_id', 'technology_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_technology');
        Schema::dropIfExists('technologies');
    }
};
