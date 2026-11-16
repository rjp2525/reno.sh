<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Social\SocialManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SocialManager::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute(300)->by('user:'.$request->user()->id)
            : Limit::perMinute(120)->by($request->ip()));
    }
}
