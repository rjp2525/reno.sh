<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education', function (Blueprint $table): void {
            $table->id();
            $table->string('school_name');
            $table->string('location')->nullable();
            $table->string('degree');
            $table->string('minor')->nullable();
            $table->date('started')->nullable();
            $table->date('graduated');
            $table->text('description')->nullable();
            $table->json('achievements')->nullable();
            $table->json('projects')->nullable();
            $table->json('extracurriculars')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education');
    }
};
