# Terminal (curl) version

Every public page has a plain text version with ANSI colors for people who visit from a terminal:

```sh
curl reno.sh
curl reno.sh/blog
curl reno.sh/blog/laravel/some-post
wget -qO- reno.sh/projects
http reno.sh/gallery            # HTTPie
xh reno.sh/contact
curl 'reno.sh/gallery/some-photo?width=120'
curl 'reno.sh/blog?nocolor' | less
curl -s reno.sh/blog | less -R  # keep the colors in less
```

The interactive version is the SSH TUI (`tui/`): `ssh reno.sh -p 2222`.

## Who gets text

`App\Http\Middleware\ServeTerminal` returns `text/plain; charset=utf-8` (with `Vary: User-Agent, Accept`) when:

- the User-Agent starts with `curl/`, `Wget/`, `HTTPie/`, `xh/` or contains `libfetch` or `PowerShell`, or
- `Accept` contains `text/plain` but not `text/html`, or
- the query has `?format=text` (no color) or `?format=ansi` (color). `?format=html` forces HTML.

Only `GET`/`HEAD` on the routes listed in `App\Terminal\Router::ROUTES` (plus the `/about` redirect).
`/admin`, `/livewire`, `/api`, `/img`, `/up`, feeds, `*.xml` and the sitemap are never touched.
Browsers get the normal Inertia pages.

## Options

| query                 | effect                                                               |
|-----------------------|----------------------------------------------------------------------|
| `?nocolor`, `?color=0`| no escape codes at all                                               |
| `?color=256`          | xterm 256 color palette instead of truecolor                         |
| `?color=16`           | the 16 basic colors                                                  |
| `?color=1`            | force truecolor (e.g. with `Accept: text/plain` from a script)        |
| `?width=100`          | wrap width, default 80, clamped to 40-160                            |
| `?page=2`             | next page on `/blog` and `/gallery`                                  |
| `?category=slug`      | filter `/gallery` (blog categories use `/blog/{category}`)           |
| `?search=term`        | search `/blog`                                                       |

Color defaults: on (truecolor) for known terminal clients, off for PowerShell and for unknown clients
that only sent `Accept: text/plain`. The commands suggested on each page carry `width` and color options
forward, so `curl 'reno.sh/?nocolor&width=60'` keeps suggesting `?nocolor&width=60` links.

Only published content is shown (posts: `status=published` and `published_at <= now`, projects and
photos: `is_published`, photos also fully processed, series: `is_published`). Anything else, or a
missing slug, renders the terminal 404 page with status 404.

## Pages

| route                         | renderer                                   |
|-------------------------------|--------------------------------------------|
| `/`                           | `Pages\HomePage`: banner, tagline, socials, latest posts, featured projects |
| `/about`, `/about/professional/{tab?}` | `Pages\ProfessionalPage`: experience, skills, education (or one `tab`) |
| `/about/personal/{tab?}`, `/about/hobbies/{tab?}` | `Pages\ContentSectionPage`: content page blocks |
| `/projects`, `/projects/{slug}` | `Pages\ProjectsPage`, `Pages\ProjectPage` |
| `/gallery`, `/gallery/{slug}` | `Pages\GalleryPage`, `Pages\PhotoPage` (EXIF, print sizes and prices, order URL) |
| `/blog`, `/blog/{category}`   | `Pages\BlogPage` with category filter      |
| `/blog/{category}/{post}`, `/blog/post/{post}` | `Pages\PostPage` (blocks, series, older/newer, link footnotes) |
| `/blog/series/{slug}`         | `Pages\SeriesPage`                         |
| `/contact`                    | `Pages\ContactPage` with a `curl -X POST /api/v1/contact` example |
| anything missing              | `Pages\NotFoundPage`                       |

Every page ends with the curl commands for the other sections and the `ssh` hint.

## Toolkit (`app/Terminal`)

- `Ansi` / `ColorMode`: palette (`primary #43D9AD`, `orange #FEA55F`, `blue #4D5BCE`, `purple`, `pink`,
  `slate`, `white`, `line`), truecolor with 256/16 color fallback, `gradient()`, nested style resets.
- `Text`: `width()` (ignores escapes, counts wide characters), `wrap()` (ANSI and multibyte aware,
  hanging indents, styles closed and re-opened at line breaks), `hardBreak()`, `breakLines()`, `pad()`,
  `truncate()`, `indent()`.
- `Layout`: `rule()`, `box()`, `keyValue()`, `code()`, `section()`.
- `Banner`: three row box-drawing font for the name and error codes.
- `HtmlConverter`: rich-text HTML to text. Handles p, h1-h6, strong/em/code/u/s, links as `text[n]`
  footnotes (collected per page and printed under `// links`), nested ul/ol, blockquote, pre/code,
  hr, img (`[image: alt] url`), tables and broken HTML (uses PHP's HTML5 parser).
- `BlockRenderer`: content blocks from `docs/api.md` (rich-text, markdown, heading, two-columns,
  callout, code, image, image-gallery, divider).

To add a page: subclass `Pages\Page` (implement `section()` and `body(int $width)`, keep every line
within `$width`), then map its route name in `Router`.

## Wiring

```php
// bootstrap/app.php, inside ->withMiddleware(...)
$middleware->web(prepend: [\App\Http\Middleware\ServeTerminal::class]);

// bootstrap/app.php, first lines inside the ->respond(...) callback, after $status is set
if (in_array($status, [403, 404, 429, 500, 503]) && ($terminal = \App\Http\Middleware\ServeTerminal::renderError(request(), $status))) {
    return $terminal;
}
```

Prepending puts it before `StartSession` and `SubstituteBindings`, so curl requests don't get a session
cookie and missing slugs reach the terminal 404 instead of the HTML error page. The `respond` hook
covers URLs that match no route.

## Tests

`php artisan test --filter=Terminal` (`tests/Feature/Terminal`).
