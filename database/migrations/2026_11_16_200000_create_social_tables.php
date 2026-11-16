<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider');
            // Provider-side id of the page/profile/actor we publish as.
            $table->string('provider_id');
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            // Encrypted JSON: access/refresh tokens, instance URL, app password, page token, ...
            $table->text('credentials');
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('needs_reauth')->default(false);
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
        });

        Schema::create('social_posts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->text('body')->nullable();
            $table->string('link_url', 2048)->nullable();
            // [{ path, mime }] on the public disk.
            $table->json('media')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->nullableUlidMorphs('source');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('social_post_targets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('social_account_id')->constrained()->cascadeOnDelete();
            $table->text('body_override')->nullable();
            $table->string('status')->default('pending');
            $table->string('provider_post_id')->nullable();
            $table->string('provider_url', 2048)->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'social_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_targets');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};
