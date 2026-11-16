# Social publishing

The admin can publish and schedule posts to Facebook Pages, Instagram professional accounts, LinkedIn (member and, optionally, Company Pages), X, Threads, Bluesky and Mastodon. This doc covers setting up each network.

## How it fits together

| Piece | Where |
| --- | --- |
| Config (app ids, scopes, API versions, timeouts) | `config/social.php` |
| One publisher per network | `app/Services/Social/Providers/*Publisher.php`, resolved by `SocialManager::driver()` |
| OAuth connect flow | `app/Services/Social/OAuth/*`, `App\Http\Controllers\Social\OAuthController` |
| Queue job (one per post × account) | `App\Jobs\PublishSocialTarget` |
| Dispatch | `SocialScheduler::dispatch()`, `php artisan social:dispatch-due` (every minute) |
| Token upkeep | `php artisan social:refresh-tokens` (daily) |

Every OAuth network uses the same callback URL pattern. Register it exactly as written (scheme and host must match `APP_URL`):

```
{APP_URL}/admin/social/callback/{provider}
```

For example `https://reno.sh/admin/social/callback/facebook`. To connect an account, visit `/admin/social/connect/{provider}` (the admin's "Connect" buttons link there).

Limits are checked before anything is sent (`SocialProvider::limits()`). Text is never truncated. A post that's too long, has too many images, or has no image for Instagram fails with a clear error on that target. Only images are supported for now. Video fails with a clear error.

Failures:

- **401 or a revoked/expired token**: the account is flagged `needs_reauth` with `last_error`, and the target fails. Reconnect the account from the admin.
- **429, 5xx or a network error**: retried up to 4 times, with backoff of 30s, 2m and 10m.
- **Anything else** (validation, duplicate content, bad media): fails right away without retrying.

---

## Meta: Facebook Pages and Instagram

One Meta app covers Facebook, Instagram and (as a separate use case) Threads.

1. Go to <https://developers.facebook.com/apps>, click **Create app**, and choose the **Business** type (or the "Manage everything on your Page" and "Manage messaging & content on Instagram" use cases in the new flow).
2. Add the **Facebook Login for Business** product. Under *Settings → Valid OAuth Redirect URIs* add:
   - `{APP_URL}/admin/social/callback/facebook`
   - `{APP_URL}/admin/social/callback/instagram`
3. Copy *App settings → Basic → App ID / App secret* into `META_APP_ID` / `META_APP_SECRET`. `META_GRAPH_VERSION` defaults to `v24.0`. Bump it when Meta deprecates a version (roughly every 2 years per version).
4. Permissions requested (see `config/social.php` → `scopes`):
   - Facebook: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `business_management`
   - Instagram: `instagram_basic`, `instagram_content_publish`, `pages_show_list`, `pages_read_engagement`, `business_management`
5. **App Review.** While the app is in Development mode it only works for people with a role on the app (admin/developer/tester), which is enough for your own Pages. To use it for other people's Pages, switch to Live mode. You'll need Advanced Access for `pages_manage_posts`, `pages_read_engagement` and `instagram_content_publish`, which means **Business Verification** plus App Review with a screencast of the publish flow and a privacy policy URL.
6. If *Require App Secret* is enabled, `appsecret_proof` is sent automatically.

Connecting:

- **Facebook**: in the consent dialog, pick the Pages to share. One account is created per Page and uses the Page access token. That token comes from a long-lived user token, so it doesn't expire unless the user changes their password, removes the app, or loses their Page role.
- **Instagram**: the account must be an Instagram **Business or Creator** account **linked to a Facebook Page**. Select that Page in the dialog. One account is created per `instagram_business_account`.

Instagram notes: every post needs at least one image (a carousel holds 2 to 10). Instagram **downloads images from public URLs**, and JPEG is the most reliable format. There's a limit of 100 API-published posts per 24 hours per account. Container processing is polled (`SOCIAL_POLL_ATTEMPTS` × `SOCIAL_POLL_INTERVAL` seconds) before publishing.

## Threads

1. In the same (or a new) Meta app, add the **Access the Threads API** use case.
2. In the use case settings, add the redirect callback URL `{APP_URL}/admin/social/callback/threads`, and add the uninstall/delete callbacks (they can point to any URL on your site; they're required fields).
3. Copy the **Threads app ID / secret** (they're *different* from the Meta app id) into `THREADS_APP_ID` / `THREADS_APP_SECRET`.
4. Scopes: `threads_basic`, `threads_content_publish`. In Development mode, add yourself as a **Threads Tester** (App roles) and accept the invite in Threads under *Settings → Account → Website permissions*. App Review is required before anyone else can connect.

Tokens are exchanged for a 60-day long-lived token, which `social:refresh-tokens` refreshes (it must be at least 24h old). Images are fetched from public URLs like Instagram. The text limit is 500 characters, and a carousel holds up to 10 items.

## LinkedIn

1. Create an app at <https://www.linkedin.com/developers/apps>. It has to be associated with a LinkedIn Company Page, and a Page admin has to verify it.
2. Under **Products**, add **Sign In with LinkedIn using OpenID Connect** and **Share on LinkedIn** (both self-serve). Together they grant `openid`, `profile` and `w_member_social`.
3. Under **Auth**, add `{APP_URL}/admin/social/callback/linkedin` as an authorized redirect URL. Copy the Client ID and Client Secret into `LINKEDIN_CLIENT_ID` / `LINKEDIN_CLIENT_SECRET`.
4. Optional, **Company Pages**: request the **Community Management API** product. It needs a separate application and review, and LinkedIn only grants it to registered legal entities. Once approved, set `LINKEDIN_ORGANIZATIONS=true`. That adds `r_organization_social`, `w_organization_social` and `rw_organization_admin`, and connects one extra account per Page you administer. Those accounts post with `meta.author_urn = urn:li:organization:{id}`.
5. `LINKEDIN_API_VERSION` (YYYYMM, default `202610`) is sent as the `LinkedIn-Version` header. LinkedIn retires versions after about a year, so bump it when you get "version not supported" errors.

Tokens last 60 days. Refresh tokens are only issued to approved partners. Without one, the account is flagged for reconnection when the token runs out. Commentary is escaped for LinkedIn's "little text" format, and hashtags become hashtag templates. A post can have 1 image, 2 to 9 images (multiImage), or a link with no images (article card).

## X

1. Create a project and app at <https://developer.x.com/en/portal/dashboard>.
2. **Posting needs a paid API tier.** The free tier allows only a few hundred posts a month per app (check the current limits). Basic or above (or pay-per-use credits) is needed for any real volume, and media upload has its own limits.
3. Under *User authentication settings*, enable **OAuth 2.0** with app permissions **Read and write** and type **Web App (confidential client)**. Set the callback URI to `{APP_URL}/admin/social/callback/x` and the website URL to your site.
4. Copy the **OAuth 2.0 Client ID / Client Secret** (not the API key/secret) into `X_CLIENT_ID` / `X_CLIENT_SECRET`.
5. Scopes: `tweet.read`, `tweet.write`, `users.read`, `media.write`, `offline.access`. The connect flow uses PKCE (S256).

Access tokens last 2 hours and are refreshed before each publish, as well as daily. X rotates refresh tokens, and the new one is stored each time. If a refresh token is revoked, the account is flagged for reconnection. URLs count as 23 characters toward the 280 limit.

## Bluesky (manual)

No app registration is needed. In the admin, add a Bluesky account with:

- **Handle**, e.g. `reno.bsky.social`, stored as `credentials.handle`
- **App password**, created at *Settings → Privacy and security → App passwords*, stored as `credentials.app_password`. Never use the main password.
- **PDS URL**, optional, stored as `credentials.pds_url`. Defaults to `https://bsky.social`. Set it for self-hosted PDSes.

The session JWTs are cached in the account's encrypted credentials and refreshed automatically. Links and hashtags become rich-text facets. A link with no images gets an external card built from the page's OpenGraph tags. Images must be under 1 MB each, with a maximum of 4.

## Mastodon (manual)

On the instance, go to *Preferences → Development → New application*, grant the scopes `write:statuses` and `write:media`, then copy **Your access token**. In the admin, add a Mastodon account with:

- **Instance URL**, e.g. `https://mastodon.social`, stored as `credentials.instance_url`
- **Access token**, stored as `credentials.access_token`

Statuses are sent with `Idempotency-Key = target id`, so retries never double-post. `MASTODON_VISIBILITY` (default `public`) sets the visibility.

---

## Local development

- **Public media URLs.** Instagram and Threads fetch images by URL from `{APP_URL}/img/{path}`, and Meta's servers can't reach `localhost`. Publishing to them with a local `APP_URL` fails early with a clear error. Expose the app through a tunnel (e.g. `cloudflared tunnel --url http://localhost:8088` or `ngrok http 8088`), set `APP_URL` to the tunnel's HTTPS URL, and run `php artisan config:clear`. Facebook, LinkedIn, X, Bluesky and Mastodon upload the image bytes directly, so they work without a tunnel.
- **OAuth callbacks.** Most networks accept `http://localhost` redirect URIs in development, but Meta requires HTTPS for anything other than localhost. Using the tunnel URL for both `APP_URL` and the registered callback is the least surprising setup. Remember to register the tunnel's callback URL with each network.
- **Queue and scheduler.** Run `php artisan queue:work` and `php artisan schedule:work` (or run `php artisan social:dispatch-due` by hand).
- **Tests** never touch the network: `php artisan test --filter=Social` (uses `Http::fake()` and `Storage::fake('public')`).

## Environment reference

| Variable | Default | Purpose |
| --- | --- | --- |
| `META_APP_ID`, `META_APP_SECRET` | | Facebook + Instagram |
| `META_GRAPH_VERSION` | `v24.0` | Graph API version |
| `THREADS_APP_ID`, `THREADS_APP_SECRET` | | Threads |
| `THREADS_GRAPH_VERSION` | `v1.0` | Threads API version |
| `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET` | | LinkedIn |
| `LINKEDIN_API_VERSION` | `202610` | `LinkedIn-Version` header |
| `LINKEDIN_ORGANIZATIONS` | `false` | Also connect Company Pages |
| `X_CLIENT_ID`, `X_CLIENT_SECRET` | | X OAuth 2.0 client |
| `BLUESKY_DEFAULT_PDS` | `https://bsky.social` | PDS used when an account doesn't set one |
| `MASTODON_VISIBILITY` | `public` | Status visibility |
| `SOCIAL_HTTP_TIMEOUT` / `_CONNECT_TIMEOUT` / `_UPLOAD_TIMEOUT` | 30 / 10 / 120 | Seconds |
| `SOCIAL_POLL_ATTEMPTS` / `SOCIAL_POLL_INTERVAL` | 10 / 3 | Container processing polls |
