<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Terminal\ColorMode;
use App\Terminal\Context;
use App\Terminal\Pages\NotFoundPage;
use App\Terminal\Pages\Page;
use App\Terminal\Router;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves an ANSI-colored plain text rendition of public pages to terminal
 * clients (`curl reno.sh`, `wget -qO- reno.sh/blog`, `http reno.sh`...).
 *
 * Detection: a terminal User-Agent, `Accept: text/plain` without text/html,
 * or `?format=text` (no color) / `?format=ansi`.
 * Options: `?nocolor` or `?color=0|16|256|1`, `?width=40..160`.
 */
class ServeTerminal
{
    /** User-Agent prefixes of terminal HTTP clients. */
    public const AGENT_PREFIXES = ['curl/', 'Wget/', 'HTTPie/', 'xh/', 'libfetch'];

    /** Substrings matched anywhere in the User-Agent: FreeBSD `fetch libfetch/2.0`, `WindowsPowerShell/5.1`. */
    public const AGENT_CONTAINS = ['libfetch', 'PowerShell'];

    /** Never served as text, whatever the client. */
    public const EXCLUDED_PATHS = ['admin', 'admin/*', 'livewire/*', 'api/*', 'img/*', 'up', 'feed', 'feed/*', '*.xml', '*.rss', 'sitemap*', 'originals/*', 'storage/*', 'build/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::eligible($request) || ! Router::handles($request->route())) {
            return $next($request);
        }

        if (! self::wantsTerminal($request)) {
            return self::vary($next($request));
        }

        $ctx = Context::fromRequest($request, self::colorMode($request));

        return self::respond(app(Router::class)->resolve($request, $ctx));
    }

    /**
     * Terminal error page for requests that never reached a route (unknown
     * URLs) or failed with an HTTP error. Returns null for everyone else so
     * the normal error page renders. Call it from the exception handler.
     */
    public static function renderError(Request $request, int $status = 404): ?Response
    {
        if (! self::eligible($request) || ! self::wantsTerminal($request)) {
            return null;
        }

        $ctx = Context::fromRequest($request, self::colorMode($request));

        return self::respond(new NotFoundPage($ctx, $request->path(), $status));
    }

    public static function eligible(Request $request): bool
    {
        return $request->isMethod('GET') || $request->isMethod('HEAD')
            ? ! $request->is(...self::EXCLUDED_PATHS)
            : false;
    }

    public static function wantsTerminal(Request $request): bool
    {
        $format = strtolower((string) $request->query('format', ''));
        if (in_array($format, ['text', 'txt', 'ansi', 'plain'], true)) {
            return true;
        }
        if (in_array($format, ['html', 'web'], true)) {
            return false;
        }

        if (self::isTerminalAgent((string) $request->userAgent())) {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        return str_contains($accept, 'text/plain') && ! str_contains($accept, 'text/html');
    }

    public static function isTerminalAgent(string $agent): bool
    {
        foreach (self::AGENT_PREFIXES as $prefix) {
            if (str_starts_with($agent, $prefix)) {
                return true;
            }
        }
        foreach (self::AGENT_CONTAINS as $needle) {
            if (str_contains($agent, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function colorMode(Request $request): ColorMode
    {
        if ($request->has('nocolor') || $request->has('no_color')) {
            return ColorMode::None;
        }

        $explicit = ColorMode::fromQuery($request->has('color') ? (string) $request->query('color') : null);
        if ($explicit !== null) {
            return $explicit;
        }

        $format = strtolower((string) $request->query('format', ''));
        if (in_array($format, ['text', 'txt', 'plain'], true)) {
            return ColorMode::None;
        }
        if ($format === 'ansi') {
            return ColorMode::TrueColor;
        }

        $agent = (string) $request->userAgent();
        if (str_contains($agent, 'PowerShell')) {
            return ColorMode::None;
        }

        // Accept: text/plain from something that isn't a known terminal
        // client is most likely a script; keep its output clean.
        return self::isTerminalAgent($agent) ? ColorMode::TrueColor : ColorMode::None;
    }

    private static function respond(Page $page): Response
    {
        return self::vary(response($page->render(), $page->status(), [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    private static function vary(Response $response): Response
    {
        $response->setVary(['User-Agent', 'Accept'], false);

        return $response;
    }
}
