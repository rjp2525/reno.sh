<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ServeTerminal;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('blog:publish-scheduled')->everyMinute();
        $schedule->command('social:dispatch-due')->everyMinute()->withoutOverlapping()->onOneServer();
        $schedule->command('social:refresh-tokens')->dailyAt('03:15')->withoutOverlapping()->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Signed by the sender (Stripe) or token-guarded (Prodigi) instead of CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // curl/wget/httpie get the ANSI terminal site. Prepended so it runs before sessions and bindings.
        $middleware->web(prepend: [ServeTerminal::class]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $exception) {
            $status = $response->getStatusCode();

            if (in_array($status, [403, 404, 429, 500, 503]) && ($terminal = ServeTerminal::renderError(request(), $status))) {
                return $terminal;
            }

            if ($exception instanceof ThrottleRequestsException && request()->inertia()) {
                return back()->with('error', 'Slow down! Please wait a moment before trying again.');
            }

            if (in_array($status, [403, 404, 500, 503]) && ! request()->is('admin', 'admin/*', 'livewire/*', 'api/*', 'webhooks/*') && ! request()->expectsJson()) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse(request())
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
