# reno.sh

Personal site of Reno Philibert: portfolio, blog, photography with print-on-demand, a native social
publishing tool, and two terminal versions of the site.

| Surface | What it is |
|---|---|
| `https://reno.sh` | Laravel 13 + Inertia 3 + Vue + Tailwind 4 site |
| `/admin` | Filament 5 panel: blog, photos, social scheduler, print shop |
| `curl reno.sh` | ANSI terminal rendering of every public page (`docs/terminal.md`) |
| `ssh reno.sh -p 2222` | Interactive TUI in Go (`tui/`) |
| `/api/v1` | JSON API for the iOS app and the TUI (`docs/api.md`) |
| [`reno.sh-ios`](https://github.com/rjp2525/reno.sh-ios) | Native iOS admin app |

## Run locally (Docker)

```bash
cp .env.example .env
composer install && pnpm install      # host tooling for IDEs/tests (optional inside Docker)
docker compose up -d                  # app, queue, scheduler, vite, postgres, mailpit
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan db:seed --class=PrintProductSeeder   # optional starter catalog
```

- Site: http://localhost:8088 · Admin: http://localhost:8088/admin (`reno@reno.sh` / `password`; set `ADMIN_EMAILS`)
- Mailpit: http://localhost:8026 · Postgres: `localhost:54320` · Vite HMR: 5188
- Terminal site: `curl localhost:8088` · SSH TUI: `docker compose --profile tui up -d tui && ssh -p 2222 localhost`

Ports are deliberately non-standard so the stack runs next to other projects; change them in `.env`.

## Tests & checks

```bash
php artisan test              # Pest: API, admin pages, prints, social, terminal
vendor/bin/pint               # code style
pnpm exec vue-tsc --noEmit    # types
(cd tui && go test ./...)
```

## Architecture

```
app/
  Http/Controllers/Api/V1   JSON API (public + token-protected admin)
  Http/Middleware/ServeTerminal.php + app/Terminal   curl/ANSI renderer
  Filament/                 admin panel (resources, pages, widgets, shared actions)
  Services/Prints           Stripe Checkout + Prodigi fulfilment
  Services/Social           composer, scheduler, OAuth and 7 network publishers
  Services/Photo            EXIF, image processing, importer
  Support/Content           content blocks: Markdown, HTML -> Markdown/text
tui/                        Go SSH app (Charm)
docs/                       api.md, social.md, terminal.md
deploy/forge/               deploy script
```

- **Content** is stored as typed blocks. Markdown blocks are rendered to sanitized HTML on save, so the
  Vue site, API, iOS app and terminals all read the same data.
- **Prints**: photos are matched to catalog sizes by aspect ratio and resolution. Checkout runs on
  Stripe; a signed webhook marks the order paid and a queued job sends it to Prodigi with a short-lived
  signed URL to the full-resolution original. Prodigi callbacks are token-guarded and re-fetched.
- **Social**: posts fan out to per-account targets; a scheduler claims due posts atomically and a job
  publishes each target with retries, token refresh and reauth detection. Setup: `docs/social.md`.

## Supply chain policy

Nothing newer than **7 days** gets installed:

- pnpm: `minimumReleaseAge` in `pnpm-workspace.yaml`
- Composer: `composer age:update` (update + automatic hold-back) / `composer age:check` (also runs after every update and in deploys)
- Go: `tui/scripts/age-check.sh`
- Docker images are pinned to tags at least a week old.

## Deploying on Laravel Forge

1. Create a PHP 8.5 server with Postgres, and a site for `reno.sh` from this repo (branch `master`)
   with zero-downtime deployments on. Make sure `storage` is a shared path (uploads and originals live there).
2. Environment: copy `.env.example`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, DB, storage
   (local by default; for Cloudflare R2 see "Storage on R2" below), `RESEND_API_KEY` (`MAIL_MAILER=resend`),
   `ADMIN_EMAILS`, Stripe, Prodigi (`PRODIGI_ENVIRONMENT=live` when ready) and social app credentials.
3. Deploy script: `deploy/forge/deploy.sh` (uses the `$CREATE_RELEASE()` / `$ACTIVATE_RELEASE()` /
   `$RESTART_QUEUES()` helpers). Enable push to deploy.
4. Queue: add a worker (`database` connection, `--tries=3 --timeout=600`).
5. Scheduler: enable the Laravel scheduler (publishes scheduled posts and social posts every minute).
   Inertia SSR: add a daemon `php artisan inertia:start-ssr` in `<site root>/current`. reno.sh listens on
   `VITE_SSR_PORT` (default 13715, not Inertia's 13714) so it can't collide with another Inertia app on the
   server; give every app its own port. The deploy script stops it after each release and the daemon restarts it.
6. SSH TUI: install Go with the `deploy/forge/install-go.sh` recipe (run as root; verifies the checksum and
   the 7-day release age). Add a daemon with the **absolute** command `<site root>/current/tui/bin/reno-tui` (Supervisor
   searches $PATH for relative commands, not the directory) and directory `<site root>/current`.
   It reads the site's `.env` (`APP_URL`, `TUI_SSH_HOST`, `TUI_SSH_PORT`), so it needs no variables of its own;
   `RENO_*` variables still override. The host key lives in `~/.reno-tui/` so it survives releases (back it up).
   Put the daemon's ID in `TUI_DAEMON_ID` in the deploy script, and open port 2222 in the firewall
   (keep the server's own sshd on 22). See `tui/README.md`.
7. Webhooks: Stripe → `https://reno.sh/webhooks/stripe` (`checkout.session.completed`,
   `checkout.session.expired`, `checkout.session.async_payment_succeeded`, `…_failed`) and put the signing
   secret in `STRIPE_WEBHOOK_SECRET`; Prodigi callbacks are set per order automatically.
8. Social OAuth redirect URI for every network: `https://reno.sh/admin/social/callback/{provider}`.

## Storage on Cloudflare R2

1. Create two buckets: a **public** one for site images (connect a custom domain such as `cdn.reno.sh`)
   and a **private** one for full-resolution photo originals (no public access; prints use signed URLs).
2. Create an R2 API token with *Object Read & Write* on both buckets.
3. Environment:
   ```
   FILESYSTEM_PUBLIC_DRIVER=s3
   PHOTO_ORIGINALS_DRIVER=s3
   AWS_ACCESS_KEY_ID=<token access key id>
   AWS_SECRET_ACCESS_KEY=<token secret>
   AWS_DEFAULT_REGION=auto
   AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
   AWS_BUCKET=<public bucket>
   FILESYSTEM_PUBLIC_URL=https://cdn.reno.sh
   PHOTO_ORIGINALS_BUCKET=<private bucket>
   ```
4. Deploy (re-caches config and restarts queue workers; saving env in Forge alone does neither), run
   `php artisan storage:check` to confirm both disks report S3/R2 and pass, then copy existing uploads once:
   `php artisan storage:migrate-local --dry-run`, then without `--dry-run`. Paths are unchanged, so
   nothing in the database needs updating. Old `/img/...` links keep working (proxied from R2).
5. **CORS** (needed for image previews in the admin, which the browser fetches cross-origin): run
   `php artisan storage:check --fix-cors`. That needs an *Admin Read & Write* token; with an
   object-only token it prints the JSON policy to paste into each bucket's **Settings → CORS policy**.
   `storage:check` reports `CORS … allowed` for both buckets once it's in place.

### Photo versions

Uploads are processed by `ProcessPhotoJob` using Laravel's native `Image` API (`config/images.php`,
Imagick preferred): a watermarked **WebP** web version (max width and quality in *Photo Settings*),
a WebP thumbnail and a 1200×630 JPEG social card. Public copies have EXIF/GPS stripped (colour
profile kept) and are stored with `Cache-Control: public, max-age=31536000, immutable`. Originals are
never modified. After changing settings, or to convert photos from before the WebP switch:
`php artisan photos:reprocess` (queued; `--all` for every photo, `--sync` to run inline).

