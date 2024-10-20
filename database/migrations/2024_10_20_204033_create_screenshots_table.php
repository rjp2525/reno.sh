<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screenshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('original_filename');
            $table->foreignIdFor(User::class, 'user_id')->nullable();
            $table->string('stored_hash')->index();
            $table->string('stored_filename');
            $table->string('stored_path')->index();
            $table->unsignedBigInteger('filesize')->nullable();
            $table->boolean('private')->default(false);
            $table->string('original_extension');
            $table->string('mime_type');
            $table->integer('height')->nullable();
            $table->integer('width')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screenshots');
    }
};
