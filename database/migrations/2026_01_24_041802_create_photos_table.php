<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();

            $table->foreignUlid('photo_category_id')
                ->nullable()
                ->constrained('photo_categories')
                ->nullOnDelete();

            // File paths
            $table->string('original_path');
            $table->string('web_path')->nullable();
            $table->string('thumbnail_path')->nullable();

            // EXIF metadata
            $table->integer('iso')->nullable();
            $table->string('aperture')->nullable();
            $table->string('shutter_speed')->nullable();
            $table->string('focal_length')->nullable();
            $table->string('camera_body')->nullable();
            $table->string('lens')->nullable();

            // GPS data
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();

            // Image metadata
            $table->timestamp('taken_at')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->bigInteger('file_size')->nullable();

            // External links
            $table->string('instagram_link')->nullable();

            // Display flags
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_published')->default(true);
            $table->integer('sort_order')->default(0);

            // Processing status
            $table->string('processing_status')->default('pending');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
