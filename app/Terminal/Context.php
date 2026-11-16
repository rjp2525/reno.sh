<?php

declare(strict_types=1);

namespace App\Terminal;

use Illuminate\Http\Request;

/**
 * Everything a terminal page needs to know about the request it renders for.
 */
final class Context
{
    public const DEFAULT_WIDTH = 80;

    public const MIN_WIDTH = 40;

    public const MAX_WIDTH = 160;

    /**
     * @param  array<string, string|true>  $options  query options to carry into suggested commands (true = bare flag)
     */
    public function __construct(
        public readonly Ansi $ansi,
        public readonly int $width = self::DEFAULT_WIDTH,
        public readonly string $host = 'reno.sh',
        public readonly string $baseUrl = 'https://reno.sh',
        public readonly array $options = [],
        public readonly ?Request $request = null,
    ) {}

    public static function fromRequest(Request $request, ColorMode $mode): self
    {
        $options = [];
        $width = self::DEFAULT_WIDTH;

        if ($request->filled('width') && is_numeric($request->query('width'))) {
            $width = max(self::MIN_WIDTH, min(self::MAX_WIDTH, (int) $request->query('width')));
            if ($width !== self::DEFAULT_WIDTH) {
                $options['width'] = (string) $width;
            }
        }

        if ($request->has('nocolor') || $request->has('no_color') || $request->has('color')) {
            $options += match ($mode) {
                ColorMode::None => ['nocolor' => true],
                ColorMode::Basic => ['color' => '16'],
                ColorMode::Ansi256 => ['color' => '256'],
                ColorMode::TrueColor => ['color' => '1'],
            };
        }

        return new self(
            ansi: new Ansi($mode),
            width: $width,
            host: $request->getHttpHost(),
            baseUrl: rtrim($request->getSchemeAndHttpHost(), '/'),
            options: $options,
            request: $request,
        );
    }

    /** `curl host/path` with this request's display options carried over. */
    public function curl(string $path, array $query = []): string
    {
        $path = '/'.ltrim($path, '/');
        $query = array_filter($query + $this->options, fn ($v) => $v !== null && $v !== '');
        $target = $this->host.($path === '/' ? '' : $path);

        if ($query === []) {
            return 'curl '.$target;
        }

        // Quoted so the shell doesn't glob the "?" or background on "&".
        $qs = implode('&', array_map(
            fn ($key, $value) => $value === true ? $key : $key.'='.rawurlencode((string) $value),
            array_keys($query),
            $query,
        ));

        return "curl '".$target.($path === '/' ? '/' : '').'?'.$qs."'";
    }

    /** Absolute web URL for a path (what you'd open in a browser). */
    public function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    public function sshHost(): string
    {
        return (string) config('site.ssh.host', 'reno.sh');
    }

    public function sshPort(): int
    {
        return (int) config('site.ssh.port', 2222);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->request?->query($key, $default) ?? $default;
    }
}
