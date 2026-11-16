<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*', 'livewire/*')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appUrl' => config('app.url'),
            // Base URL for stored images (CDN on R2, /img proxy locally); see imageUrl() in resources/js/lib/utils.ts.
            'storageUrl' => rtrim(Storage::disk('public')->url(''), '/'),
            'currentUrl' => $request->url(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
