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

1. Create a PHP 8.5 server with Postgres, and a site for `reno.sh` from this repo (branch `master`).
2. Environment: copy `.env.example`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, DB, S3
   (`FILESYSTEM_PUBLIC_DRIVER=s3`, `PHOTO_ORIGINALS_DRIVER=s3`), `RESEND_API_KEY` (`MAIL_MAILER=resend`),
   `ADMIN_EMAILS`, Stripe, Prodigi (`PRODIGI_ENVIRONMENT=live` when ready) and social app credentials.
3. Deploy script: `deploy/forge/deploy.sh`. Enable Quick Deploy.
4. Queue: add a worker (`database` connection, `--tries=3 --timeout=600`).
5. Scheduler: enable the Laravel scheduler (publishes scheduled posts and social posts every minute).
6. SSH TUI: install Go, add a daemon `reno-tui` running `/home/forge/reno.sh/tui/bin/reno-tui` with
   `RENO_API_URL=https://reno.sh RENO_SSH_PORT=2222 RENO_HOST_KEY_PATH=/home/forge/.reno-tui/host_ed25519`,
   then open port 2222 in the Forge firewall (keep the server's own sshd on 22). See `tui/README.md`.
7. Webhooks: Stripe → `https://reno.sh/webhooks/stripe` (`checkout.session.completed`,
   `checkout.session.expired`, `checkout.session.async_payment_succeeded`, `…_failed`) and put the signing
   secret in `STRIPE_WEBHOOK_SECRET`; Prodigi callbacks are set per order automatically.
8. Social OAuth redirect URI for every network: `https://reno.sh/admin/social/callback/{provider}`.
