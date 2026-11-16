<?php

use App\Http\Middleware\ServeTerminal;
use App\Models\Post;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    app('router')->prependMiddlewareToGroup('web', ServeTerminal::class);
});

dataset('terminal agents', [
    'curl' => 'curl/8.7.1',
    'wget' => 'Wget/1.24.5',
    'httpie' => 'HTTPie/3.2.4',
    'xh' => 'xh/0.22.2',
    'fetch' => 'fetch libfetch/2.0',
    'powershell' => 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.2506',
]);

it('serves plain text to terminal clients', function (string $agent) {
    $response = $this->get('/', ['User-Agent' => $agent]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($response->getVary())->toContain('User-Agent', 'Accept')
        ->and($response->getContent())->not->toContain('<html')->toContain('_hello')
        ->and($response->headers->getCookies())->toBeEmpty();
})->with('terminal agents');

it('serves html to browsers', function () {
    $response = $this->get('/', [
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/html')
        ->and($response->getVary())->toContain('User-Agent')
        ->and($response->getContent())->toContain('<html');
});

it('serves text for Accept: text/plain without text/html', function () {
    $response = $this->get('/blog', ['User-Agent' => 'my-script/1.0', 'Accept' => 'text/plain']);

    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($response->getContent())->not->toContain("\e[");
});

it('keeps html when text/plain is accepted alongside text/html', function () {
    $response = $this->get('/blog', ['User-Agent' => 'Mozilla/5.0', 'Accept' => 'text/html, text/plain;q=0.5']);

    expect($response->headers->get('Content-Type'))->toContain('text/html');
});

it('honours ?format=text and ?format=ansi in a browser', function () {
    $agent = ['User-Agent' => 'Mozilla/5.0', 'Accept' => 'text/html'];

    $text = $this->get('/?format=text', $agent);
    expect($text->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($text->getContent())->not->toContain("\e[");

    $ansi = $this->get('/?format=ansi', $agent);
    expect($ansi->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($ansi->getContent())->toContain("\e[38;2;");
});

it('colors curl output and not powershell output', function () {
    expect($this->get('/', ['User-Agent' => 'curl/8.7.1'])->getContent())->toContain("\e[");
    expect($this->get('/', ['User-Agent' => 'Mozilla/5.0 WindowsPowerShell/5.1'])->getContent())->not->toContain("\e[");
});

it('supports color fallbacks', function () {
    $curl = ['User-Agent' => 'curl/8.7.1'];

    expect($this->get('/?color=256', $curl)->getContent())->toContain("\e[38;5;")->not->toContain("\e[38;2;");
    expect($this->get('/?color=16', $curl)->getContent())->toMatch('/\e\[(?:1;)?9[0-7]m|\e\[(?:1;)?3[0-7]m/')->not->toContain("\e[38;");
});

it('disables color with ?nocolor and ?color=0', function (string $query) {
    expect($this->get('/'.$query, ['User-Agent' => 'curl/8.7.1'])->getContent())->not->toContain("\e[");
})->with(['?nocolor', '?color=0', '?color=false', '?no_color']);

it('never serves text for excluded paths', function (string $path) {
    $request = Request::create($path, 'GET', server: ['HTTP_USER_AGENT' => 'curl/8.7.1']);

    expect(ServeTerminal::eligible($request))->toBeFalse();
})->with(['/admin', '/admin/posts', '/livewire/update', '/api/v1/site', '/img/photos/a.jpg', '/up', '/feed', '/blog/feed.xml', '/sitemap.xml']);

it('leaves non GET requests alone', function () {
    $request = Request::create('/contact', 'POST', server: ['HTTP_USER_AGENT' => 'curl/8.7.1']);

    expect(ServeTerminal::eligible($request))->toBeFalse();
});

it('serves the feed and sitemap untouched to curl', function () {
    Post::factory()->published()->create();

    $response = $this->get('/sitemap.xml', ['User-Agent' => 'curl/8.7.1']);

    expect($response->headers->get('Content-Type'))->not->toContain('text/plain');
});

it('returns a text body for HEAD requests', function () {
    $response = $this->call('HEAD', '/', server: ['HTTP_USER_AGENT' => 'curl/8.7.1']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8');
});

it('renders terminal error pages for unmatched urls via renderError', function () {
    $request = Request::create('/definitely/not/here', 'GET', server: ['HTTP_USER_AGENT' => 'curl/8.7.1']);
    $response = ServeTerminal::renderError($request, 404);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($response->getContent())->toContain('page not found');

    $browser = Request::create('/definitely/not/here', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0', 'HTTP_ACCEPT' => 'text/html']);
    expect(ServeTerminal::renderError($browser))->toBeNull();
});

it('renders the terminal 404 for unknown urls once wired into the exception handler', function () {
    // Mirrors the line suggested for bootstrap/app.php's ->respond() callback.
    app(ExceptionHandler::class)->respondUsing(
        fn (Response $response, Throwable $e, Request $request) => ServeTerminal::renderError($request, $response->getStatusCode()) ?? $response,
    );

    $curl = $this->get('/no/such/page?nocolor', ['User-Agent' => 'curl/8.7.1']);
    $curl->assertNotFound();
    expect($curl->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($curl->getContent())->toContain('cd: no such file or directory: /no/such/page');

    $browser = $this->get('/no/such/page', ['User-Agent' => 'Mozilla/5.0', 'Accept' => 'text/html']);
    expect($browser->headers->get('Content-Type'))->not->toContain('text/plain');
});
